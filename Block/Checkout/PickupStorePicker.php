<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Block\Checkout;

use ETechFlow\InStorePickup\Api\StoreRepositoryInterface;
use ETechFlow\InStorePickup\Model\Config;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Hyvä Checkout pickup-store picker — server-rendered + Alpine.js (v1.3.0).
 *
 * Supersedes the v1.0.x – v1.2.x Magewire\Checkout\StorePicker component,
 * which was architecturally incompatible with Hyvä Checkout's Magewire
 * bootstrap pipeline (Hyvä Checkout only hydrates components implementing
 * its form-abstraction interfaces; extending \Magewirephp\Magewire\Component
 * directly meant the picker silently failed to mount on every install).
 *
 * The architectural fix: drop the Magewire round-trip entirely. The store
 * list is static (just the set of active stores) and the "selected store"
 * doesn't actually drive any server-side state — the auto-fill plugin
 * runs from the shipping-method commit, which carries the store code in
 * the carrier method (etechflow_isp_<store_code>). All the Magewire
 * component ever did with selection was decorate the UI; we can do that
 * with Alpine.js client-side.
 *
 * How it wires up:
 *
 *   1. This block renders a server-side <ul> of all active stores as cards.
 *   2. The template's Alpine.js `x-data` tracks which card is "selected"
 *      (purely visual state).
 *   3. Clicking a card calls `pick(<store_code>)`, which:
 *      a. Updates `selected` for visual highlighting.
 *      b. Finds the matching shipping-method radio
 *         (input[type=radio][value="etechflow_isp_<code>"]) and triggers
 *         its click() + change event.
 *      c. Standard Magento shipping-method commit fires, which triggers
 *         our ShippingAddressAutofillPlugin (Phase 7), which overwrites
 *         the shipping address with the picked store's address.
 *   4. The radio list stays visible below as a fallback / parallel UI —
 *      customers can click either the card or the radio with identical
 *      results. (Hyvä Commerce admin styles the cards prominently so they
 *      look like the primary picker.)
 */
class PickupStorePicker extends Template
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly StoreRepositoryInterface $storeRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Master visibility. Same composition as the v1.2.x Magewire component:
     * module-level isEnabled() must be true; without active stores we hide
     * the whole panel rather than render an empty container.
     */
    public function isPickerEnabled(): bool
    {
        if (!$this->config->isEnabled()) {
            return false;
        }
        return !empty($this->getActiveStores());
    }

    /**
     * Active pickup stores flattened for the template's foreach. Same data
     * shape as the v1.2.x Magewire component returned, so existing template
     * markup translates 1:1.
     *
     * @return array<int, array{
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
    public function getActiveStores(): array
    {
        $records = [];
        foreach ($this->storeRepository->getAllActive() as $store) {
            $records[] = [
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
        return $records;
    }

    /**
     * Format a single store as a single-line readable address. Template
     * uses this for consistency with the v1.2.x Magewire version's
     * formatAddress() method.
     *
     * @param array<string, string> $store
     * @return string
     */
    public function formatAddress(array $store): string
    {
        $parts = array_filter([
            $store['street']   ?? '',
            $store['city']     ?? '',
            $store['postcode'] ?? '',
            $store['country']  ?? '',
        ], static fn(string $p): bool => $p !== '');
        return implode(', ', $parts);
    }

    /**
     * Carrier method prefix — the autofill plugin's Phase 7 contract uses
     * `etechflow_isp_<store_code>` as the shipping-method code, so Alpine
     * needs to find radios with that value when card is clicked.
     */
    public function getMethodPrefix(): string
    {
        return 'etechflow_isp_';
    }
}
