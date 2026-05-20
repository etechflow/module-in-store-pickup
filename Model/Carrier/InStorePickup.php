<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\Carrier;

use ETechFlow\InStorePickup\Api\Data\StoreInterface;
use ETechFlow\InStorePickup\Api\StoreRepositoryInterface;
use ETechFlow\InStorePickup\Model\Config;
use ETechFlow\InStorePickup\Model\Performance\Profiler;
use Magento\Framework\App\Config\ScopeConfigInterface;
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
 * Registered under `carriers/etechflow_isp/`. Returns ONE shipping method
 * per active store, so a multi-store merchant exposes a list like:
 *
 *   ( ) Pick up at Keystation Main      Free
 *   ( ) Pick up at Keystation North     Free
 *   ( ) Pick up at Auto Remote Man      Free
 *
 * Customer picks the radio for their preferred store. The method code
 * format is `etechflow_isp_<store_code>` — the Phase 7 auto-fill plugin
 * extracts the store code from that and overwrites the shipping address
 * with the store's address (fixing the universal wrong-tax bug).
 *
 * Cost is 0 by default in v1.0 (configurable per-store in v1.1). A
 * merchant who charges for pickup (rare) can configure a flat fee
 * via standard `carriers/etechflow_isp/price` config in v1.1.
 *
 * Every code path is wrapped in try/catch — a misconfigured store or
 * DB hiccup degrades to "no pickup rate" instead of crashing checkout.
 *
 * Standalone-first architecture: this carrier works regardless of NDE
 * / DD / BED installation. Optional stock-check enhancement via NDE is
 * wired in Phase 10.
 */
class InStorePickup extends AbstractCarrier implements CarrierInterface
{
    /** @var string */
    protected $_code = 'etechflow_isp';

    /** Multi-method carrier — one method per active store. */
    protected $_isFixed = false;

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
     * Get the list of allowed methods for admin shipping-method dropdowns +
     * Cart Price Rule conditions. Returns one entry per active store.
     *
     * @return array<string, string> code => name
     */
    public function getAllowedMethods(): array
    {
        $methods = [];
        try {
            foreach ($this->storeRepository->getAllActive() as $store) {
                $code = $store->getCode();
                if ($code !== '') {
                    $methods[$code] = (string) __('Pick up at %1', $store->getName());
                }
            }
        } catch (\Throwable $e) {
            $this->_logger->error(
                'ETechFlow_InStorePickup: getAllowedMethods failed.',
                ['exception' => $e->getMessage()]
            );
        }
        return $methods;
    }

    /**
     * Collect shipping rates for the given request.
     *
     * @param RateRequest $request
     * @return Result|false
     */
    public function collectRates(RateRequest $request)
    {
        // Module-level kill-switch + license check (delegates to Config)
        if (!$this->config->isEnabled()) {
            return false;
        }

        // Carrier-level enable flag — wired to active in config.xml.
        // Standard Magento "disable carrier" admin UI works automatically.
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $span = Profiler::start('ETechFlow_ISP_collectRates');
        try {
            $result = $this->rateResultFactory->create();
            $appended = 0;

            $activeStores = $this->storeRepository->getAllActive();
            if (empty($activeStores)) {
                return false;
            }

            foreach ($activeStores as $store) {
                $methodCode = $store->getCode();
                if ($methodCode === '') {
                    continue;
                }

                $result->append($this->buildRateMethod($store));
                $appended++;
            }

            if ($appended === 0) {
                return false;
            }

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
     * Translate a Store into a Magento Rate\Method row.
     *
     * @param StoreInterface $store
     * @return RateMethod
     */
    private function buildRateMethod(StoreInterface $store): RateMethod
    {
        /** @var RateMethod $rate */
        $rate = $this->rateMethodFactory->create();
        $rate->setCarrier($this->_code);
        $rate->setCarrierTitle((string) ($this->getConfigData('title') ?: $this->config->getMethodTitle()));
        $rate->setMethod($store->getCode());
        $rate->setMethodTitle((string) __('Pick up at %1', $store->getName()));

        // v1.0: free pickup. Per-store flat fees ship in v1.1 via a
        // `cost` field on the Store record + this lookup.
        $cost = 0.0;
        $rate->setCost($cost);
        $rate->setPrice($cost);

        // Surface the store's pickup instructions to the customer where
        // Magento's checkout supports method_description.
        $description = trim((string) ($store->getPickupInstructions() ?? ''));
        if ($description !== '') {
            $rate->setData('method_description', $description);
        }

        return $rate;
    }
}
