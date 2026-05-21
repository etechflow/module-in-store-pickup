<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Validates the per-domain license key for ETechFlow_InStorePickup.
 *
 * Identical pattern to every other eTechFlow module:
 *   - Per-module key activates this module only.
 *   - Bundle key (shared HMAC secret across modules) activates ALL
 *     eTechFlow modules at once.
 *   - "Production Environment = No" bypasses licensing for dev/staging.
 *   - Common dev hostnames auto-detect and bypass licensing.
 *
 * Invalid licence → the carrier disappears from checkout, admin grid
 * banner shows the unlicensed state, but storefront never crashes.
 */
class LicenseValidator
{
    public const XML_PATH_LICENSE_KEY            = 'etechflow_instorepickup/license/license_key';
    public const XML_PATH_PRODUCTION_ENVIRONMENT = 'etechflow_instorepickup/license/production_environment';

    /** Shared config path — same value across all eTechFlow modules. */
    public const XML_PATH_BUNDLE_LICENSE_KEY = 'etechflow_bundle/license/license_key';

    /** Admin autofill (v1.1). */
    public const XML_PATH_GETADDRESS_API_KEY = 'etechflow_instorepickup/admin_autofill/getaddress_api_key';
    public const XML_PATH_DEFAULT_COUNTRY    = 'etechflow_instorepickup/admin_autofill/default_country';

    private const MODULE_ID = 'in-store-pickup';

    /** Shared bundle identifier — must match across all eTechFlow modules. */
    private const BUNDLE_ID = 'etechflow-bundle';

    /** Per-module HMAC secret. */
    private const SECRET_FRAGMENTS = [
        'eTF-ISP-2026',
        'h7N3-vS9k',
        'C4xR-bL6p',
        'W2zQ-fM8e',
    ];

    /** Shared bundle HMAC secret. MUST be identical across every eTechFlow module. */
    private const BUNDLE_SECRET_FRAGMENTS = [
        'eTF-BUNDLE-2026',
        'k2D9-mP4x',
        'L8nR-vH2j',
        'X7tY-zW5q',
    ];

    /**
     * @param ScopeConfigInterface  $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        $host = $this->getCurrentHost();
        if ($host === '') {
            return false;
        }
        if (!$this->isProductionEnvironment()) {
            return true;
        }
        if ($this->isDevelopmentHost($host)) {
            return true;
        }
        $configuredKey = $this->getConfiguredKey();
        if ($configuredKey !== '' && hash_equals($this->computeKey($host), $configuredKey)) {
            return true;
        }
        $bundleKey = $this->getConfiguredBundleKey();
        if ($bundleKey !== '' && hash_equals($this->computeBundleKey($host), $bundleKey)) {
            return true;
        }
        return false;
    }

    /**
     * @param string $host
     * @return string
     */
    public function computeKey(string $host): string
    {
        $payload = $this->canonicalize($host) . ':' . self::MODULE_ID;
        $secret  = implode('', self::SECRET_FRAGMENTS);
        $raw     = hash_hmac('sha256', $payload, $secret, true);
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * @param string $host
     * @return string
     */
    public function computeBundleKey(string $host): string
    {
        $payload = $this->canonicalize($host) . ':' . self::BUNDLE_ID;
        $secret  = implode('', self::BUNDLE_SECRET_FRAGMENTS);
        $raw     = hash_hmac('sha256', $payload, $secret, true);
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * @param string $host
     * @return string
     */
    private function canonicalize(string $host): string
    {
        $host = strtolower(trim($host));
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
        return $host;
    }

    /**
     * @return string
     */
    public function getConfiguredKey(): string
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_LICENSE_KEY, ScopeInterface::SCOPE_STORE);
        return trim((string) $value);
    }

    /**
     * @return string
     */
    public function getConfiguredBundleKey(): string
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_BUNDLE_LICENSE_KEY, ScopeInterface::SCOPE_STORE);
        return trim((string) $value);
    }

    /**
     * @return bool
     */
    public function isProductionEnvironment(): bool
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_PRODUCTION_ENVIRONMENT, ScopeInterface::SCOPE_STORE);
        if ($value === null || $value === '') {
            return true;
        }
        return (bool) $value;
    }

    /**
     * @return string
     */
    public function getCurrentHost(): string
    {
        try {
            $url = $this->storeManager->getStore()->getBaseUrl();
            $host = parse_url($url, PHP_URL_HOST);
            return is_string($host) ? strtolower($host) : '';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * @param string|null $host
     * @return bool
     */
    public function isDevHost(?string $host = null): bool
    {
        $check = $host !== null
            ? $this->canonicalize($host)
            : $this->canonicalize($this->getCurrentHost());
        return $this->isDevelopmentHost($check);
    }

    /**
     * @param string $host
     * @return bool
     */
    private function isDevelopmentHost(string $host): bool
    {
        if ($host === 'localhost' || str_starts_with($host, '127.')) {
            return true;
        }
        if (str_starts_with($host, '10.') || str_starts_with($host, '192.168.')) {
            return true;
        }
        if (preg_match('/^172\.(1[6-9]|2[0-9]|3[01])\./', $host)) {
            return true;
        }
        foreach (['.test', '.local', '.localhost', '.dev', '.example', '.invalid'] as $s) {
            if (str_ends_with($host, $s)) return true;
        }
        foreach (['staging.', 'stage.', 'dev.', 'qa.', 'uat.', 'test.', 'preview.', 'sandbox.'] as $p) {
            if (str_starts_with($host, $p)) return true;
        }
        if (preg_match('/-(staging|stage|dev|qa|uat|test|preview|sandbox)\./', $host)) return true;
        foreach (['.magento.cloud', '.magentocloud.com', '.cloud.magento'] as $s) {
            if (str_ends_with($host, $s)) return true;
        }
        foreach (['.ngrok.io', '.ngrok-free.app', '.loca.lt', '.serveo.net'] as $s) {
            if (str_ends_with($host, $s)) return true;
        }
        return false;
    }
}
