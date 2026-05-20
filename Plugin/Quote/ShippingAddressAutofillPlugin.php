<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Plugin\Quote;

use ETechFlow\InStorePickup\Api\StoreRepositoryInterface;
use ETechFlow\InStorePickup\Model\Config;
use ETechFlow\InStorePickup\Model\Performance\Profiler;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Address;
use Psr\Log\LoggerInterface;

/**
 * THE KILLER FEATURE: auto-fill shipping address from picked store.
 *
 * Every existing C&C module on the Magento marketplace shares the same
 * structural bug — when a customer picks "in-store pickup", they're still
 * required to fill in a shipping address. They type their home address,
 * Magento charges tax based on it, the merchant has to manually fix every
 * order. This plugin solves that.
 *
 * Mechanism:
 *
 *   1. Customer picks a shipping method on the address form
 *   2. setShippingMethod() fires with code `etechflow_isp_<store_code>`
 *   3. We extract the store code, load the Store entity
 *   4. We OVERWRITE the address fields (street, city, postcode, region,
 *      country) with the store's address — silently
 *   5. Magento's tax engine subsequently calculates tax based on the
 *      STORE'S address, not the customer's home address
 *   6. Order is placed with correct tax + correct fulfillment routing
 *
 * Only fires when:
 *   - The module is licensed + enabled
 *   - `autofill_shipping_address` admin config = Yes (default on)
 *   - The method starts with `etechflow_isp_`
 *
 * Failure modes (silent + logged):
 *   - Store code doesn't match a real store → leave address unchanged
 *   - Store has incomplete address → leave changed fields unchanged
 *   - Any exception → leave address unchanged
 */
class ShippingAddressAutofillPlugin
{
    /** Prefix we look for on the shipping-method code. */
    public const METHOD_PREFIX = 'etechflow_isp_';

    public function __construct(
        private readonly Config $config,
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Intercept `Address::setShippingMethod($method)` AFTER Magento has set
     * the method on the address. If the method points to an in-store pickup,
     * overwrite the address fields with the store's address.
     *
     * @param Address $subject
     * @param Address $result   The address object (setShippingMethod returns $this)
     * @param string|null $method
     * @return Address
     */
    public function afterSetShippingMethod(Address $subject, $result, $method = null): Address
    {
        // Defensive: not our method? Bail immediately.
        if (!is_string($method) || !str_starts_with($method, self::METHOD_PREFIX)) {
            return $result;
        }

        // License + module-enabled gate.
        if (!$this->config->isEnabled()) {
            return $result;
        }

        // Merchant can opt out via admin config.
        if (!$this->config->isAutofillShippingAddress()) {
            return $result;
        }

        $span = Profiler::start('ETechFlow_ISP_AutofillAddress');
        try {
            $storeCode = substr($method, strlen(self::METHOD_PREFIX));
            if ($storeCode === '') {
                return $result;
            }

            try {
                $store = $this->storeRepository->getByCode($storeCode);
            } catch (NoSuchEntityException $e) {
                // Method code references a store that no longer exists.
                // Log + leave the address as-is rather than guess.
                $this->logger->warning(
                    'ETechFlow_InStorePickup: autofill skipped — store code not found.',
                    ['method' => $method, 'store_code' => $storeCode]
                );
                return $result;
            }

            // Apply each address field — but only when the store has a
            // value AND the field is one we care about for tax.
            $street = (string) ($store->getStreet() ?? '');
            if ($street !== '') {
                $result->setStreet([$street]);
            }
            $city = (string) ($store->getCity() ?? '');
            if ($city !== '') {
                $result->setCity($city);
            }
            $region = (string) ($store->getRegion() ?? '');
            if ($region !== '') {
                $result->setRegion($region);
            }
            $postcode = (string) ($store->getPostcode() ?? '');
            if ($postcode !== '') {
                $result->setPostcode($postcode);
            }
            $country = (string) ($store->getCountryCode() ?? '');
            if ($country !== '') {
                $result->setCountryId($country);
            }

            // Magento's tax engine reads address from quote address.
            // Force it to recollect totals so the new address is used
            // for the next tax calculation.
            $quote = $result->getQuote();
            if ($quote !== null && method_exists($quote, 'setTotalsCollectedFlag')) {
                $quote->setTotalsCollectedFlag(false);
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                'ETechFlow_InStorePickup: autofill plugin failed; leaving address unchanged.',
                ['exception' => $e->getMessage(), 'method' => $method]
            );
        } finally {
            Profiler::stop($span);
        }

        return $result;
    }
}
