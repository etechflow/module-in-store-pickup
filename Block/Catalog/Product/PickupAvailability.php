<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Block\Catalog\Product;

use ETechFlow\InStorePickup\Api\StoreRepositoryInterface;
use ETechFlow\InStorePickup\Model\Config;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * PDP "Click & Collect available" widget (v1.2.0).
 *
 * Two render modes, controlled by admin config:
 *
 *   - Mode A — "simple":      static "Click & Collect available at our shops" notice.
 *   - Mode B — "per_store":   list active pickup stores with per-source MSI stock for
 *                             the current product. Falls back to Mode A's text per-store
 *                             when a store has no msi_source_code mapped.
 *
 * v1.2.1 — local-stock gate: when `pdp_widget/require_local_stock = 1` (default),
 * the whole widget is suppressed for a product that has no local stock (qty <= 0
 * or out of stock). This mirrors the checkout rule (NDE ShippingRestriction) that
 * removes the pickup shipping method for out-of-local-stock items — a product
 * fulfilled from a remote supplier can't be picked up from the shop, so we don't
 * advertise Click & Collect for it on the PDP either. Container product types
 * (configurable / bundle / grouped) are exempt: their stock lives on the child
 * items, so the parent's own qty is not a reliable signal.
 *
 * MSI is soft-detected so the module installs without Magento_InventoryApi (rare on
 * 2.3+ but possible on stripped builds). Without MSI, Mode B silently degrades to
 * "Available at: Maldon, Chelmsford, Witham" — same useful UX, no stock numbers.
 */
class PickupAvailability extends Template
{
    public const MODE_SIMPLE    = 'simple';
    public const MODE_PER_STORE = 'per_store';

    /** Product types whose stock is carried by their child items, not the parent. */
    private const CONTAINER_TYPES = ['configurable', 'bundle', 'grouped'];

    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly Registry $registry,
        private readonly StockRegistryInterface $stockRegistry,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Master visibility — block renders nothing if false. Composes the module-
     * level enable flag, the per-widget PDP toggle, and (v1.2.1) the local-stock
     * gate for the current product.
     */
    public function isWidgetEnabled(): bool
    {
        if (!$this->config->isEnabled()) {
            return false;
        }
        if (!$this->config->isPdpWidgetEnabled()) {
            return false;
        }
        if ($this->config->isPdpRequireLocalStock() && !$this->hasLocalStockForCurrentProduct()) {
            return false;
        }
        return true;
    }

    /**
     * @return self::MODE_SIMPLE | self::MODE_PER_STORE
     */
    public function getDisplayMode(): string
    {
        $mode = $this->config->getPdpWidgetDisplayMode();
        return $mode === self::MODE_PER_STORE ? self::MODE_PER_STORE : self::MODE_SIMPLE;
    }

    /**
     * Active pickup stores, flattened for the template. In Mode B each entry
     * also carries a stock_qty (int or null when MSI isn't mapped for that
     * store).
     *
     * @return array<int, array{name:string, code:string, has_msi:bool, stock_qty:?int}>
     */
    public function getStores(): array
    {
        $stores = [];
        foreach ($this->storeRepository->getAllActive() as $store) {
            $stores[] = [
                'name'      => (string) $store->getName(),
                'code'      => (string) $store->getCode(),
                'has_msi'   => $store->getMsiSourceCode() !== null && $store->getMsiSourceCode() !== '',
                'stock_qty' => null,
            ];
        }

        if ($this->getDisplayMode() !== self::MODE_PER_STORE) {
            return $stores;
        }

        $sku = $this->getCurrentProductSku();
        if ($sku === null || $sku === '') {
            return $stores;
        }

        $stockMap = $this->loadStockBySourceForSku($sku);
        foreach ($stores as $i => $store) {
            if (!$store['has_msi']) {
                continue;  // stay null — template will skip the stock badge for this row
            }
            $sourceCode = (string) ($this->storeRepository->getByCode($store['code'])->getMsiSourceCode() ?? '');
            $stores[$i]['stock_qty'] = $stockMap[$sourceCode] ?? 0;
        }

        return $stores;
    }

    /**
     * Tiny convenience for the template's Mode A counter.
     */
    public function getStoreCount(): int
    {
        return count($this->getStores());
    }

    /**
     * Whether the current PDP product has local stock available for pickup.
     *
     * Returns true (don't suppress) when we can't make a confident negative
     * call — no product in registry, a container product type, a missing
     * stock row read error, etc. Only a definitive "qty <= 0 / out of stock"
     * returns false. Fail-open by design: a glitch should never wrongly hide
     * a valid Click & Collect option.
     */
    private function hasLocalStockForCurrentProduct(): bool
    {
        $product = $this->registry->registry('current_product');
        if ($product === null || !$product->getId()) {
            return true;
        }

        // Container types carry stock on their children; the parent qty is not
        // a reliable signal, so never suppress on it.
        if (in_array((string) $product->getTypeId(), self::CONTAINER_TYPES, true)) {
            return true;
        }

        try {
            $stockItem = $this->stockRegistry->getStockItem((int) $product->getId());
            if (!$stockItem || !$stockItem->getItemId()) {
                // No CatalogInventory row at all → treat as no local stock,
                // matching the checkout ineligibility check.
                return false;
            }
            return $stockItem->getIsInStock() && (float) $stockItem->getQty() > 0;
        } catch (\Throwable $e) {
            return true; // fail open
        }
    }

    /**
     * @return string|null
     */
    private function getCurrentProductSku(): ?string
    {
        $product = $this->registry->registry('current_product');
        if ($product === null) {
            return null;
        }
        $sku = (string) $product->getSku();
        return $sku !== '' ? $sku : null;
    }

    /**
     * Returns source_code => qty for the given SKU.
     *
     * Soft-detects Magento_InventoryApi so the module survives on stripped
     * Magento builds without MSI. Returns an empty map on any failure —
     * the block falls back to "available at X stores" without stock numbers.
     *
     * @return array<string, int>
     */
    private function loadStockBySourceForSku(string $sku): array
    {
        if (!interface_exists('\Magento\InventoryApi\Api\GetSourceItemsBySkuInterface')) {
            return [];
        }
        try {
            /** @var \Magento\InventoryApi\Api\GetSourceItemsBySkuInterface $svc */
            $svc = ObjectManager::getInstance()
                ->get(\Magento\InventoryApi\Api\GetSourceItemsBySkuInterface::class);
            $items = $svc->execute($sku);
            $map = [];
            foreach ($items as $item) {
                $map[(string) $item->getSourceCode()] = (int) $item->getQuantity();
            }
            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
