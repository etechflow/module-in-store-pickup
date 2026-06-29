<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\Carrier;

use ETechFlow\InStorePickup\Api\Data\StoreInterface;
use ETechFlow\InStorePickup\Api\StoreRepositoryInterface;
use ETechFlow\InStorePickup\Model\Config;
use ETechFlow\InStorePickup\Model\Performance\Profiler;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method as RateMethod;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory as RateMethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Psr\Log\LoggerInterface;

/**
 * Magento Shipping carrier — In-Store Pickup.
 *
 * v2.0 architecture change: returns ONE method (`etechflow_isp_pickup`)
 * instead of one method per store. The customer picks their store + slot
 * via a modal triggered by selecting the radio — those choices are stored
 * on the quote (`quote.etechflow_isp_pickup_store_id` and
 * `quote.etechflow_isp_pickup_at`) and reflected in the method title
 * once chosen:
 *
 *   ( ) Pick up in store                              £0.00   ← initial
 *
 *   ( ) Pick up: Keystation Maldon — Tue 27 May 14:00 £0.00   ← after modal
 *       [ Change ]
 *
 * Why ONE method instead of N: the checkout shipping list stays clean
 * (1 radio for pickup, alongside Royal Mail's 3 radios — total 4) regardless
 * of how many physical stores the merchant operates. With 20 stores, the
 * old approach produced 23 radios; the new approach still has 4.
 *
 * No selection yet → method title is the bare "Pick up in store". A plugin
 * (Plugin/Quote/PickupValidator) blocks order placement until the customer
 * has chosen a store + slot via the modal.
 *
 * Cost is 0.00. Per-store flat fees defer to v2.1.
 */
class InStorePickup extends AbstractCarrier implements CarrierInterface
{
    public const METHOD_CODE = 'pickup';
    public const FULL_METHOD_CODE = 'etechflow_isp_pickup';

    /** @var string */
    protected $_code = 'etechflow_isp';

    /** Single-method carrier in v2.0. */
    protected $_isFixed = true;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        private readonly ResultFactory $rateResultFactory,
        private readonly RateMethodFactory $rateMethodFactory,
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * Allowed methods — v2.0 returns the single "pickup" method.
     *
     * Admin shipping-method dropdowns + Cart Price Rule conditions see
     * `etechflow_isp_pickup` and nothing else.
     *
     * @return array<string, string>
     */
    public function getAllowedMethods(): array
    {
        return [self::METHOD_CODE => (string) __('Pick up in store')];
    }

    /**
     * Collect shipping rates for the given request.
     *
     * @return Result|false
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->config->isEnabled()) {
            return false;
        }
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $span = Profiler::start('ETechFlow_ISP_collectRates');
        try {
            // At least one active store must exist; otherwise hide the carrier.
            $activeStores = $this->storeRepository->getAllActive();
            if (empty($activeStores)) {
                return false;
            }

            // Hide In-Store Pickup when any cart item has no local store stock
            // (qty <= 0). You can't collect what isn't physically in the store.
            // Mirrors the PDP availability widget's stock gate so PDP and
            // checkout agree.
            if (!$this->cartHasLocalStock($request)) {
                return false;
            }

            $result = $this->rateResultFactory->create();
            $result->append($this->buildSingleRateMethod($request));
            return $result;
        } catch (\Throwable $e) {
            $this->_logger->error(
                'ETechFlow_InStorePickup: collectRates failed; returning no rates.',
                ['exception' => $e->getMessage()]
            );
            return false;
        } finally {
            Profiler::stop($span);
        }
    }

    /**
     * True only if every physical cart item has local store stock (qty > 0).
     *
     * Uses legacy CatalogInventory — the same source as the PDP availability
     * widget — so PDP and checkout agree on what is collectable. Fail-open per
     * item on a transient lookup error; a definitive qty <= 0 (or a missing
     * stock row) hides pickup. Composite parents are skipped (their child rows
     * carry the stock); virtual/downloadable items are ignored.
     *
     * @return bool true = whole cart is in local stock (offer pickup)
     */
    private function cartHasLocalStock(RateRequest $request): bool
    {
        $items = $request->getAllItems() ?: [];
        if (empty($items)) {
            return true;
        }

        $stockRegistry = \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\CatalogInventory\Api\StockRegistryInterface::class);

        foreach ($items as $item) {
            // Composite parent (configurable/bundle): its child rows carry stock.
            if (method_exists($item, 'getHasChildren') && $item->getHasChildren()) {
                continue;
            }
            $product = $item->getProduct();
            if ($product !== null && $product->getIsVirtual()) {
                continue;
            }
            try {
                $stockItem = $stockRegistry->getStockItem((int) $item->getProductId());
            } catch (\Throwable $e) {
                continue; // transient lookup error -> do not wrongly hide pickup
            }
            if (!$stockItem
                || !$stockItem->getItemId()
                || !$stockItem->getIsInStock()
                || (float) $stockItem->getQty() <= 0
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build the single rate method. Title reflects the quote's pickup
     * selection if present — otherwise the bare default.
     */
    private function buildSingleRateMethod(RateRequest $request): RateMethod
    {
        /** @var RateMethod $rate */
        $rate = $this->rateMethodFactory->create();
        $rate->setCarrier($this->_code);
        $rate->setCarrierTitle((string) ($this->getConfigData('title') ?: $this->config->getMethodTitle()));
        $rate->setMethod(self::METHOD_CODE);

        $title = $this->resolveMethodTitle($request);
        $rate->setMethodTitle($title);

        $rate->setCost(0.0);
        $rate->setPrice(0.0);
        return $rate;
    }

    /**
     * Resolve the method title — reflects quote's pickup selection if set.
     *
     * No selection → "Pick up in store"
     * Selection set → "Pick up: <Store Name> — <Pretty Datetime>"
     */
    private function resolveMethodTitle(RateRequest $request): string
    {
        $bare = (string) __('Pick up in store');

        // Try to find the quote via the rate request's items.
        $quote = null;
        $items = $request->getAllItems();
        if (is_array($items) && !empty($items)) {
            $first = reset($items);
            if ($first && method_exists($first, 'getQuote')) {
                $quote = $first->getQuote();
            }
        }
        if (!$quote || !method_exists($quote, 'getData')) {
            return $bare;
        }

        $storeId = (int) $quote->getData('etechflow_isp_pickup_store_id');
        $pickupAt = (string) $quote->getData('etechflow_isp_pickup_at');
        if ($storeId <= 0 || $pickupAt === '') {
            return $bare;
        }

        try {
            $store = $this->storeRepository->getById($storeId);
        } catch (NoSuchEntityException $e) {
            return $bare;
        }

        $pretty = $this->prettifyDateTime($pickupAt);
        return (string) __('Pick up: %1 — %2', $store->getName(), $pretty);
    }

    /**
     * Format a stored datetime as "Tue 27 May 14:00".
     */
    private function prettifyDateTime(string $dateTime): string
    {
        try {
            $dt = new \DateTime($dateTime);
            return $dt->format('D j M H:i');
        } catch (\Throwable $e) {
            return $dateTime;
        }
    }
}
