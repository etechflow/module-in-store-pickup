<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Magewire\Checkout;

use ETechFlow\InStorePickup\Api\Data\StoreInterface;
use ETechFlow\InStorePickup\Api\StoreRepositoryInterface;
use ETechFlow\InStorePickup\Model\Config;
use ETechFlow\InStorePickup\Model\Performance\Profiler;

/**
 * @deprecated since v1.3.0 — superseded by
 *             {@see \ETechFlow\InStorePickup\Block\Checkout\PickupStorePicker}
 *             (plain Block + Alpine.js, no Magewire dependency).
 *
 * This class extended \Magewirephp\Magewire\Component directly, which is
 * incompatible with Hyvä Checkout's Magewire bootstrap pipeline (Hyvä
 * Checkout only hydrates components implementing its form-abstraction
 * interfaces). Result: every install from v1.0.0 through v1.2.1 silently
 * rendered the bare shipping-method radio fallback at checkout instead of
 * the rich card UI, even when the layout container name was correct.
 *
 * v1.3.0 sidestepped the architectural mismatch entirely by switching to
 * a regular Block + Alpine.js client-side state. The new picker works on
 * Hyvä Checkout, Hyvä Theme, and any Magento checkout that includes
 * Alpine.js — no framework-specific bootstrap required.
 *
 * Kept in place for backwards-compatibility on the off-chance an integrator
 * referenced this class directly. Safe to delete in a future major release.
 *
 * Magewire-native store picker for Hyvä Checkout (legacy).
 *
 * The second true Magewire component in the eTechFlow suite (after DD's
 * DeliveryDatePicker v1.4.0). Same architecture: public state lives on
 * the server, every customer click round-trips via wire:click + Magewire
 * re-renders the relevant section.
 *
 * Render contract:
 *
 *   - Renders inside Hyvä Checkout's `checkout.shipping.methods.before` slot
 *     (the v1.2.1 fix — earlier releases referenced `checkout.shipping.before`
 *     which doesn't exist in Hyvä Checkout, so the picker silently failed to
 *     mount and customers got the plain radio fallback).
 *   - On `mount()`: load active stores once. Populates $stores as a
 *     flat array of [code, name, address lines, phone, instructions].
 *   - Customer clicks a store card → `wire:click="pickStore('code')"` →
 *     server validates the code, sets $selectedStoreCode, the card
 *     highlights, the auto-fill plugin runs on the address.
 *
 * Coexistence with the non-Magewire fallback:
 *
 *   - Stores without Hyvä Checkout (Luma, plain Hyvä Theme) get the
 *     standard Magento shipping-method radio list — one method per
 *     active store, courtesy of our Carrier (Phase 6).
 *   - This Magewire component only loads via `hyva_checkout_components.xml`
 *     which is only triggered by Hyvä Checkout's layout handle.
 *
 * Standalone — the picker does NOT require NDE / DD / BED to render.
 * Sibling-module data (NDE eligibility, DD slots, BED ETAs) layers in
 * via the optional adapters (Phase 10).
 *
 * Lifecycle hooks used:
 *   - `mount()` — fires on initial Hyvä Checkout page render. Loads
 *     the active-store list once.
 *
 * Wire actions exposed:
 *   - pickStore(string $code)   — pick a store from the list
 *   - clearSelection()          — clear selection (re-show the full list)
 */
class StorePicker extends \Magewirephp\Magewire\Component
{
    // -----------------------------------------------------------------
    // Public state — auto-synced server ↔ browser.
    // -----------------------------------------------------------------

    /** Currently selected store code, or '' for none. */
    public string $selectedStoreCode = '';

    /** Master enable flag — propagated to the template for early-exit. */
    public bool $enabled = false;

    /**
     * Active-store list, flattened for the template.
     *
     * Magewire serialises public arrays to the browser as JSON, so we
     * carry only the fields the template actually renders — no Magento
     * model references.
     *
     * @var array<int, array{
     *     code:string,
     *     name:string,
     *     street:string,
     *     city:string,
     *     postcode:string,
     *     country:string,
     *     phone:string,
     *     instructions:string
     * }>
     */
    public array $stores = [];

    /**
     * Cached lookup of the selected store's instructions, so the
     * template can render the "you'll be emailed when ready" block
     * without an extra round-trip on every render.
     */
    public string $selectedInstructions = '';

    /** Selected store's display name (for the "Picked: <name>" header). */
    public string $selectedName = '';

    // -----------------------------------------------------------------
    // Injected dependencies.
    // -----------------------------------------------------------------

    public function __construct(
        private readonly Config $config,
        private readonly StoreRepositoryInterface $storeRepository
    ) {
    }

    /**
     * Magewire lifecycle hook — fires once on first render.
     *
     * @return void
     */
    public function mount(): void
    {
        if (!$this->config->isEnabled()) {
            $this->enabled = false;
            return;
        }
        $this->enabled = true;

        $span = Profiler::start('ETechFlow_ISP_MagewireMount');
        try {
            $this->loadStores();
        } finally {
            Profiler::stop($span);
        }
    }

    // -----------------------------------------------------------------
    // Wire actions.
    // -----------------------------------------------------------------

    /**
     * Customer clicked a store card.
     *
     * Validates the code against the loaded list (rejects tampered
     * client state), updates $selectedStoreCode, and pre-populates the
     * selection-display fields. The auto-fill shipping-address plugin
     * (Phase 7) runs separately when the customer commits the
     * shipping-method choice via Hyvä Checkout's standard flow.
     *
     * @param string $code
     * @return void
     */
    public function pickStore(string $code): void
    {
        if (!$this->enabled || $code === '') {
            return;
        }

        foreach ($this->stores as $store) {
            if ($store['code'] === $code) {
                $this->selectedStoreCode = $code;
                $this->selectedName = $store['name'];
                $this->selectedInstructions = $store['instructions'];
                return;
            }
        }
        // Code not in loaded list — silently ignore (probably stale client state).
    }

    /**
     * Reset selection back to "no store picked".
     *
     * @return void
     */
    public function clearSelection(): void
    {
        $this->selectedStoreCode = '';
        $this->selectedName = '';
        $this->selectedInstructions = '';
    }

    // -----------------------------------------------------------------
    // Render helpers (template-callable, computed on every render).
    // -----------------------------------------------------------------

    /**
     * Format a single store as a single-line readable address.
     *
     * Template uses this so it doesn't have to assemble street/city/postcode
     * itself — keeps the template simple.
     *
     * @param array<string, string> $store
     * @return string
     */
    public function formatAddress(array $store): string
    {
        $parts = array_filter([
            $store['street'] ?? '',
            $store['city'] ?? '',
            $store['postcode'] ?? '',
            $store['country'] ?? '',
        ], static fn(string $p): bool => $p !== '');
        return implode(', ', $parts);
    }

    // -----------------------------------------------------------------
    // Private.
    // -----------------------------------------------------------------

    /**
     * Load all active stores from the repository and shape them for
     * the template's array iteration.
     *
     * @return void
     */
    private function loadStores(): void
    {
        $records = [];
        foreach ($this->storeRepository->getAllActive() as $store) {
            $records[] = $this->serialiseStore($store);
        }
        $this->stores = $records;
    }

    /**
     * @param StoreInterface $store
     * @return array{
     *     code:string,
     *     name:string,
     *     street:string,
     *     city:string,
     *     postcode:string,
     *     country:string,
     *     phone:string,
     *     instructions:string
     * }
     */
    private function serialiseStore(StoreInterface $store): array
    {
        return [
            'code'         => (string) $store->getCode(),
            'name'         => (string) $store->getName(),
            'street'       => (string) ($store->getStreet() ?? ''),
            'city'         => (string) ($store->getCity() ?? ''),
            'postcode'     => (string) ($store->getPostcode() ?? ''),
            'country'      => (string) ($store->getCountryCode() ?? ''),
            'phone'        => (string) ($store->getPhone() ?? ''),
            'instructions' => (string) ($store->getPickupInstructions() ?? ''),
        ];
    }
}
