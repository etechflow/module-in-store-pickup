<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Console\Command;

use ETechFlow\InStorePickup\Api\AmenityRepositoryInterface;
use ETechFlow\InStorePickup\Api\HolidayRepositoryInterface;
use ETechFlow\InStorePickup\Api\PickupWindowRepositoryInterface;
use ETechFlow\InStorePickup\Api\StoreRepositoryInterface;
use ETechFlow\InStorePickup\Api\TagRepositoryInterface;
use ETechFlow\InStorePickup\Model\Config;
use ETechFlow\InStorePickup\Model\LicenseValidator;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State as AppState;
use Magento\Framework\ObjectManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/magento etechflow:isp:verify`
 *
 * Headless end-to-end check of the ETechFlow_InStorePickup module foundation.
 * v1.0 Phase 1: confirms license + config + all 11 DB tables exist.
 *
 * As the module gains admin CRUD + checkout integration in subsequent
 * phases, this verify CLI grows to exercise: store CRUD round-trip,
 * carrier registration, auto-fill plugin, optional integrations
 * (NDE / DD / BED), and the pickup-ready email pipeline.
 */
class VerifyCommand extends Command
{
    /** All 11 DB tables created by db_schema.xml. */
    private const REQUIRED_TABLES = [
        'etechflow_isp_store',
        'etechflow_isp_store_hours',
        'etechflow_isp_store_exception',
        'etechflow_isp_holiday',
        'etechflow_isp_store_holiday_exclusion',
        'etechflow_isp_amenity',
        'etechflow_isp_store_amenity',
        'etechflow_isp_tag',
        'etechflow_isp_store_tag',
        'etechflow_isp_pickup_window',
        'etechflow_isp_store_pickup_window',
    ];

    public function __construct(
        private readonly AppState $appState,
        private readonly LicenseValidator $licenseValidator,
        private readonly Config $config,
        private readonly ResourceConnection $resource,
        private readonly ObjectManagerInterface $objectManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('etechflow:isp:verify')
            ->setDescription('Headless end-to-end check of the ETechFlow In-Store Pickup module.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        }

        $output->writeln('<info>=== ETechFlow In-Store Pickup verify ===</info>');
        $output->writeln('');

        $allPassed = true;

        try {
            $this->step($output, '1. LicenseValidator evaluates without throwing');
            $host    = $this->licenseValidator->getCurrentHost();
            $isDev   = $this->licenseValidator->isDevHost();
            $isValid = $this->licenseValidator->isValid();
            $this->pass($output, sprintf(
                'host=%s; dev_host=%s; valid=%s',
                $host !== '' ? $host : '(empty)',
                $isDev ? 'yes' : 'no',
                $isValid ? 'yes' : 'no'
            ));

            $this->step($output, '2. Config.isEnabled() returns a boolean');
            $enabled = $this->config->isEnabled();
            $this->pass($output, 'enabled=' . ($enabled ? 'yes' : 'no'));

            $this->step($output, '3. Method-title + autofill + window-required settings reachable');
            $title  = $this->config->getMethodTitle();
            $auto   = $this->config->isAutofillShippingAddress();
            $needWindow = $this->config->isPickupWindowRequired();
            $this->pass($output, sprintf(
                'title="%s"; autofill=%s; require_window=%s',
                $title, $auto ? 'yes' : 'no', $needWindow ? 'yes' : 'no'
            ));

            $this->step($output, '4. Integration detection (NDE / DD / BED) works without crashing');
            $useNde = $this->config->isUseNdeEligibility();
            $useDd  = $this->config->isUseDdTimeIntervals();
            $useBed = $this->config->isUseBedEta();
            $this->pass($output, sprintf(
                'NDE=%s; DD=%s; BED=%s',
                $useNde ? 'on' : 'off (or not installed)',
                $useDd  ? 'on' : 'off (or not installed)',
                $useBed ? 'on' : 'off (or not installed)'
            ));

            $this->step($output, sprintf('5. All %d DB tables exist', count(self::REQUIRED_TABLES)));
            $connection = $this->resource->getConnection();
            $missing = [];
            foreach (self::REQUIRED_TABLES as $tableName) {
                $fullName = $this->resource->getTableName($tableName);
                if (!$connection->isTableExists($fullName)) {
                    $missing[] = $tableName;
                }
            }
            if (!empty($missing)) {
                throw new \RuntimeException('Missing tables: ' . implode(', ', $missing) . ' — run bin/magento setup:upgrade');
            }
            $this->pass($output, count(self::REQUIRED_TABLES) . ' tables present');

            $this->step($output, '6. Stores table has the expected columns');
            $storeColumns = $connection->describeTable($this->resource->getTableName('etechflow_isp_store'));
            $expectedCols = ['store_id', 'code', 'name', 'is_active', 'postcode', 'msi_source_code'];
            $missingCols  = array_diff($expectedCols, array_keys($storeColumns));
            if (!empty($missingCols)) {
                throw new \RuntimeException('Stores table missing columns: ' . implode(', ', $missingCols));
            }
            $this->pass($output, count($storeColumns) . ' columns present');

            // ---- Phase 4 checks: all 5 entity repositories instantiate via DI ----
            $this->step($output, '7. StoreRepository instantiates via DI');
            $repo = $this->objectManager->get(StoreRepositoryInterface::class);
            $this->pass($output, get_class($repo));

            $this->step($output, '8. TagRepository instantiates via DI');
            $repo = $this->objectManager->get(TagRepositoryInterface::class);
            $this->pass($output, get_class($repo));

            $this->step($output, '9. AmenityRepository instantiates via DI');
            $repo = $this->objectManager->get(AmenityRepositoryInterface::class);
            $this->pass($output, get_class($repo));

            $this->step($output, '10. PickupWindowRepository instantiates via DI');
            $repo = $this->objectManager->get(PickupWindowRepositoryInterface::class);
            $this->pass($output, get_class($repo));

            $this->step($output, '11. HolidayRepository instantiates via DI');
            $repo = $this->objectManager->get(HolidayRepositoryInterface::class);
            $this->pass($output, get_class($repo));

            $output->writeln('');
            // ---- Phase 6-7 checks: carrier + auto-fill plugin ----
            $this->step($output, '12. Shipping carrier (etechflow_isp) instantiates via DI');
            $carrier = $this->objectManager->get(\ETechFlow\InStorePickup\Model\Carrier\InStorePickup::class);
            if (!$carrier instanceof \Magento\Shipping\Model\Carrier\AbstractCarrier) {
                throw new \RuntimeException('Carrier does not extend AbstractCarrier');
            }
            $this->pass($output, 'getAllowedMethods returns ' . count($carrier->getAllowedMethods()) . ' methods');

            $this->step($output, '13. Auto-fill plugin class resolves');
            $plugin = $this->objectManager->get(\ETechFlow\InStorePickup\Plugin\Quote\ShippingAddressAutofillPlugin::class);
            if (!$plugin instanceof \ETechFlow\InStorePickup\Plugin\Quote\ShippingAddressAutofillPlugin) {
                throw new \RuntimeException('Auto-fill plugin DI returned wrong type');
            }
            $this->pass($output);

            $output->writeln('');
            $output->writeln('<info>✅ ALL CHECKS PASSED. v1.0.0 Phase 6-7 (carrier + auto-fill) verified.</info>');
        } catch (\Throwable $e) {
            $allPassed = false;
            $output->writeln('');
            $output->writeln('<error>❌ FAIL: ' . $e->getMessage() . '</error>');
            $output->writeln('<error>at ' . $e->getFile() . ':' . $e->getLine() . '</error>');
        }

        return $allPassed ? Command::SUCCESS : Command::FAILURE;
    }

    private function step(OutputInterface $output, string $label): void
    {
        $output->write('  ' . $label . ' ... ');
    }

    private function pass(OutputInterface $output, string $detail = ''): void
    {
        $output->writeln('<info>OK</info>' . ($detail !== '' ? " ({$detail})" : ''));
    }
}
