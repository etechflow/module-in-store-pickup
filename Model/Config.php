<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Admin-config wrapper for ETechFlow_InStorePickup.
 *
 * `isEnabled()` consults the licence validator first — an unlicensed
 * install silently hides the pickup carrier from checkout regardless
 * of the admin enable flag.
 */
class Config
{
    private const XML_PATH_ENABLED                   = 'etechflow_instorepickup/general/enabled';
    private const XML_PATH_METHOD_TITLE              = 'etechflow_instorepickup/general/pickup_method_title';
    private const XML_PATH_METHOD_DESCRIPTION        = 'etechflow_instorepickup/general/pickup_method_description';
    private const XML_PATH_REQUIRE_PICKUP_WINDOW     = 'etechflow_instorepickup/general/require_pickup_window';
    private const XML_PATH_AUTOFILL_SHIPPING_ADDRESS = 'etechflow_instorepickup/general/autofill_shipping_address';
    private const XML_PATH_PICKUP_CODE_LENGTH        = 'etechflow_instorepickup/general/pickup_code_length';
    private const XML_PATH_USE_NDE_ELIGIBILITY       = 'etechflow_instorepickup/integrations/use_nde_eligibility';
    private const XML_PATH_USE_DD_TIME_INTERVALS     = 'etechflow_instorepickup/integrations/use_dd_time_intervals';
    private const XML_PATH_USE_BED_ETA               = 'etechflow_instorepickup/integrations/use_bed_eta';
    private const XML_PATH_SEND_PICKUP_READY         = 'etechflow_instorepickup/notifications/send_pickup_ready';
    private const XML_PATH_SEND_STAFF_ALERT          = 'etechflow_instorepickup/notifications/send_staff_alert';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LicenseValidator $licenseValidator
    ) {
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        if (!$this->licenseValidator->isValid()) {
            return false;
        }
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getMethodTitle(?int $storeId = null): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_PATH_METHOD_TITLE, ScopeInterface::SCOPE_STORE, $storeId)
            ?: 'Pick up in store');
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getMethodDescription(?int $storeId = null): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_PATH_METHOD_DESCRIPTION, ScopeInterface::SCOPE_STORE, $storeId)
            ?: 'Collect your order from one of our shops');
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isPickupWindowRequired(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_REQUIRE_PICKUP_WINDOW, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isAutofillShippingAddress(?int $storeId = null): bool
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_AUTOFILL_SHIPPING_ADDRESS, ScopeInterface::SCOPE_STORE, $storeId);
        if ($value === null || $value === '') {
            return true;  // default on — fixes the wrong-tax bug
        }
        return (bool) $value;
    }

    /**
     * @param int|null $storeId
     * @return int 4-8 — defensive clamp
     */
    public function getPickupCodeLength(?int $storeId = null): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_PATH_PICKUP_CODE_LENGTH, ScopeInterface::SCOPE_STORE, $storeId);
        return max(4, min(8, $value ?: 6));
    }

    /**
     * Whether ISP should consult NDE's stock-eligibility rules engine when
     * checking if a product is picklable. Only applies if NDE is installed.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isUseNdeEligibility(?int $storeId = null): bool
    {
        if (!class_exists('\ETechFlow\NextDayEligibility\Model\IneligibilityChecker', false)
            && !$this->isClassAvailable('\ETechFlow\NextDayEligibility\Model\IneligibilityChecker')) {
            return false;
        }
        return $this->scopeConfig->isSetFlag(self::XML_PATH_USE_NDE_ELIGIBILITY, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Whether ISP should reuse DD's time intervals as pickup-window slots.
     * Only applies if DD is installed.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isUseDdTimeIntervals(?int $storeId = null): bool
    {
        if (!$this->isClassAvailable('\ETechFlow\DeliveryDate\Api\TimeIntervalRepositoryInterface')) {
            return false;
        }
        return $this->scopeConfig->isSetFlag(self::XML_PATH_USE_DD_TIME_INTERVALS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Whether ISP should pull pickup-ready date from BED's per-product ETA
     * for backorder items. Only applies if BED is installed.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isUseBedEta(?int $storeId = null): bool
    {
        if (!$this->isClassAvailable('\ETechFlow\BackorderEtaDisplay\Model\EtaResolver')) {
            return false;
        }
        return $this->scopeConfig->isSetFlag(self::XML_PATH_USE_BED_ETA, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isSendPickupReadyEmail(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_SEND_PICKUP_READY, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isSendStaffAlert(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_SEND_STAFF_ALERT, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Soft-detect a sibling eTechFlow module's class without triggering
     * autoload errors when the module isn't installed.
     *
     * @param string $class Fully-qualified class name (with leading backslash)
     * @return bool
     */
    private function isClassAvailable(string $class): bool
    {
        return class_exists($class) || interface_exists($class);
    }
}
