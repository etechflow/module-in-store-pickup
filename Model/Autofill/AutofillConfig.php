<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\Autofill;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Reader for the v1.1 admin-autofill config group.
 *
 * Kept separate from the main `Model\Config` (which the legacy ISP code
 * already uses heavily) so this autofill add-on doesn't require touching
 * the existing Config class.
 */
class AutofillConfig
{
    public const XML_PATH_GETADDRESS_API_KEY = 'etechflow_instorepickup/admin_autofill/getaddress_api_key';
    public const XML_PATH_DEFAULT_COUNTRY    = 'etechflow_instorepickup/admin_autofill/default_country';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Returns the decrypted plaintext getAddress.io API key.
     * Empty string when not configured (the postcode lookup feature falls
     * back to "disabled" gracefully).
     */
    public function getGetAddressApiKey(): string
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_GETADDRESS_API_KEY, ScopeInterface::SCOPE_STORE);
        return trim((string) $value);
    }

    public function hasGetAddressApiKey(): bool
    {
        return $this->getGetAddressApiKey() !== '';
    }

    /**
     * Default country ISO 2-letter code for new pickup-store forms.
     * Falls back to GB (United Kingdom) — that's the primary target market.
     */
    public function getDefaultCountry(): string
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_DEFAULT_COUNTRY, ScopeInterface::SCOPE_STORE);
        $value = strtoupper(trim((string) $value));
        return $value !== '' ? $value : 'GB';
    }
}
