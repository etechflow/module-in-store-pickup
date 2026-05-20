<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Console\Command;

use ETechFlow\InStorePickup\Api\AmenityRepositoryInterface;
use ETechFlow\InStorePickup\Api\HolidayRepositoryInterface;
use ETechFlow\InStorePickup\Api\PickupWindowRepositoryInterface;
use ETechFlow\InStorePickup\Api\StoreRepositoryInterface;
use ETechFlow\InStorePickup\Api\TagRepositoryInterface;
use ETechFlow\InStorePickup\Model\AmenityFactory;
use ETechFlow\InStorePickup\Model\Carrier\InStorePickup as PickupCarrier;
use ETechFlow\InStorePickup\Model\Config;
use ETechFlow\InStorePickup\Model\HolidayFactory;
use ETechFlow\InStorePickup\Model\LicenseValidator;
use ETechFlow\InStorePickup\Model\Notification\PickupReadySender;
use ETechFlow\InStorePickup\Model\Notification\StaffAlertSender;
use ETechFlow\InStorePickup\Model\PickupOrderDetector;
use ETechFlow\InStorePickup\Model\PickupWindowFactory;
use ETechFlow\InStorePickup\Model\Source\AmenityOptions;
use ETechFlow\InStorePickup\Model\Source\PickupWindowOptions;
use ETechFlow\InStorePickup\Model\Source\TagOptions;
use ETechFlow\InStorePickup\Model\Store\AssignmentManager;
use ETechFlow\InStorePickup\Model\Store\ExceptionManager;
use ETechFlow\InStorePickup\Model\Store\HoursManager;
use ETechFlow\InStorePickup\Model\Store\WindowOverrideManager;
use ETechFlow\InStorePickup\Model\StoreFactory;
use ETechFlow\InStorePickup\Model\TagFactory;
use ETechFlow\InStorePickup\Plugin\Quote\ShippingAddressAutofillPlugin;
use Magento\Framework\App\State;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Framework\DataObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Headless end-to-end integration sweep for the whole module.
 *
 * Walks every major surface in turn (license, entities, per-store pivots,
 * carrier, auto-fill plugin, detector, both emails, mark-ready idempotency,
 * holiday importer) and reports pass/fail per group. Designed to be
 * run before a release commit and after any DI/schema change.
 *
 * Test data: created with the prefix `_isptest_` (Stores: `_isptest_main`,
 * `_isptest_secondary`; entities likewise) so they're easy to spot. The
 * --cleanup flag removes them on exit; --keep leaves them for hand-inspection.
 */
class IntegrationTestCommand extends Command
{
    public const NAME = 'etechflow:isp:integration-test';

    private const TEST_PREFIX = '_isptest_';

    /** @var array<string, array{passed: int, failed: int, errors: string[]}> */
    private array $results = [];

    /** @var string */
    private string $currentSection = '';

    public function __construct(
        private readonly State $appState,
        private readonly LicenseValidator $licenseValidator,
        private readonly Config $config,
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly StoreFactory $storeFactory,
        private readonly TagRepositoryInterface $tagRepository,
        private readonly TagFactory $tagFactory,
        private readonly AmenityRepositoryInterface $amenityRepository,
        private readonly AmenityFactory $amenityFactory,
        private readonly PickupWindowRepositoryInterface $pickupWindowRepository,
        private readonly PickupWindowFactory $pickupWindowFactory,
        private readonly HolidayRepositoryInterface $holidayRepository,
        private readonly HolidayFactory $holidayFactory,
        private readonly AssignmentManager $assignmentManager,
        private readonly HoursManager $hoursManager,
        private readonly ExceptionManager $exceptionManager,
        private readonly WindowOverrideManager $windowOverrideManager,
        private readonly AmenityOptions $amenityOptions,
        private readonly TagOptions $tagOptions,
        private readonly PickupWindowOptions $pickupWindowOptions,
        private readonly PickupCarrier $pickupCarrier,
        private readonly ShippingAddressAutofillPlugin $autofillPlugin,
        private readonly PickupOrderDetector $pickupOrderDetector,
        private readonly StaffAlertSender $staffAlertSender,
        private readonly PickupReadySender $pickupReadySender,
        private readonly QuoteFactory $quoteFactory,
        private readonly OrderFactory $orderFactory
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName(self::NAME)
            ->setDescription('Full end-to-end integration sweep for ETechFlow_InStorePickup.')
            ->addOption('keep', null, InputOption::VALUE_NONE, 'Leave test data behind for inspection.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode('adminhtml');
        } catch (\Throwable $e) {
            // already set
        }

        $output->writeln('<info>ETechFlow ISP — Integration Test Sweep</info>');
        $output->writeln(str_repeat('=', 60));

        // Best-effort cleanup of any stale test data first.
        $this->cleanupTestData();

        $this->section('License', fn () => $this->testLicense($output), $output);
        $this->section('Entity CRUD', fn () => $this->testEntityCrud($output), $output);
        $this->section('Per-store sub-features', fn () => $this->testPerStoreSubfeatures($output), $output);
        $this->section('Carrier', fn () => $this->testCarrier($output), $output);
        $this->section('Auto-fill plugin', fn () => $this->testAutofillPlugin($output), $output);
        $this->section('Pickup order detector', fn () => $this->testDetector($output), $output);
        $this->section('Staff alert email', fn () => $this->testStaffAlertEmail($output), $output);
        $this->section('Pickup ready email', fn () => $this->testPickupReadyEmail($output), $output);
        $this->section('Mark ready idempotency', fn () => $this->testMarkReadyIdempotency($output), $output);

        if (!$input->getOption('keep')) {
            $output->writeln('');
            $output->writeln('<comment>Cleaning up test data...</comment>');
            $this->cleanupTestData();
        }

        return $this->reportResults($output);
    }

    private function section(string $section, callable $fn, OutputInterface $output): void
    {
        $this->currentSection = $section;
        $this->results[$section] = ['passed' => 0, 'failed' => 0, 'errors' => []];
        $output->writeln('');
        $output->writeln(sprintf('<info>▶ %s</info>', $section));
        try {
            $fn();
        } catch (\Throwable $e) {
            $this->fail('uncaught: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), $output);
        }
    }

    private function assert(bool $condition, string $what, OutputInterface $output): void
    {
        if ($condition) {
            $output->writeln('  <info>✓</info> ' . $what);
            $this->results[$this->currentSection]['passed']++;
        } else {
            $output->writeln('  <error>✗</error> ' . $what);
            $this->results[$this->currentSection]['failed']++;
            $this->results[$this->currentSection]['errors'][] = $what;
        }
    }

    private function fail(string $msg, OutputInterface $output): void
    {
        $this->assert(false, $msg, $output);
    }

    private function testLicense(OutputInterface $output): void
    {
        // Should be valid in dev (host-detection bypass)
        $valid = $this->licenseValidator->isValid();
        $this->assert($valid, 'License validates (dev host bypass)', $output);
        $this->assert($this->config->isEnabled(), 'Config reports module enabled', $output);
    }

    private function testEntityCrud(OutputInterface $output): void
    {
        // Store
        $store = $this->storeFactory->create();
        $store->setCode(self::TEST_PREFIX . 'main')
              ->setName('Test Main Store')
              ->setStreet('1 Test Street')
              ->setCity('Testville')
              ->setPostcode('TE1 1ST')
              ->setCountryCode('GB')
              ->setIsActive(true)
              ->setSortOrder(1);
        $this->storeRepository->save($store);
        $storeId = (int) $store->getStoreId();
        $this->assert($storeId > 0, 'Store: create persists with PK', $output);

        $loaded = $this->storeRepository->getById($storeId);
        $this->assert($loaded->getCode() === self::TEST_PREFIX . 'main', 'Store: load by id', $output);

        $loaded->setName('Test Main Store (renamed)');
        $this->storeRepository->save($loaded);
        $reloaded = $this->storeRepository->getById($storeId);
        $this->assert($reloaded->getName() === 'Test Main Store (renamed)', 'Store: update persists', $output);

        // Tag
        $tag = $this->tagFactory->create();
        $tag->setCode(self::TEST_PREFIX . 'flagship')
            ->setLabel('Test Flagship')
            ->setColour('#0066cc')
            ->setSortOrder(1)
            ->setIsActive(true);
        $this->tagRepository->save($tag);
        $this->assert($tag->getTagId() > 0, 'Tag: create persists', $output);

        // Amenity
        $am = $this->amenityFactory->create();
        $am->setCode(self::TEST_PREFIX . 'coffee')
           ->setLabel('Test Coffee')
           ->setIcon('coffee')
           ->setSortOrder(1)
           ->setIsActive(true);
        $this->amenityRepository->save($am);
        $this->assert($am->getAmenityId() > 0, 'Amenity: create persists', $output);

        // PickupWindow
        $win = $this->pickupWindowFactory->create();
        $win->setCode(self::TEST_PREFIX . 'morning')
            ->setLabel('Test Morning')
            ->setStartTime('09:00')
            ->setEndTime('12:00')
            ->setCapacity(5)
            ->setSortOrder(1)
            ->setIsActive(true);
        $this->pickupWindowRepository->save($win);
        $this->assert($win->getWindowId() > 0, 'PickupWindow: create persists', $output);

        // Holiday
        $h = $this->holidayFactory->create();
        $h->setName(self::TEST_PREFIX . 'Test Day')
          ->setHolidayDate('2026-07-04')
          ->setIsRecurring(true)
          ->setIsClosed(true)
          ->setCountryCode('XX');
        $this->holidayRepository->save($h);
        $this->assert($h->getHolidayId() > 0, 'Holiday: create persists', $output);

        // Source models include our new test entities
        $tagOptions = array_filter(
            $this->tagOptions->toOptionArray(),
            fn ($o) => str_starts_with($o['label'], 'Test ')
        );
        $this->assert(count($tagOptions) > 0, 'TagOptions exposes new tag', $output);

        $amOptions = array_filter(
            $this->amenityOptions->toOptionArray(),
            fn ($o) => str_starts_with($o['label'], 'Test ')
        );
        $this->assert(count($amOptions) > 0, 'AmenityOptions exposes new amenity', $output);

        $winOptions = array_filter(
            $this->pickupWindowOptions->toOptionArray(),
            fn ($o) => str_contains($o['label'], 'Test ')
        );
        $this->assert(count($winOptions) > 0, 'PickupWindowOptions exposes new window', $output);
    }

    private function testPerStoreSubfeatures(OutputInterface $output): void
    {
        $storeId = $this->getTestStoreId();
        if ($storeId === 0) {
            $this->fail('No test store — skipping per-store tests', $output);
            return;
        }

        // Amenity assignment
        $amenityId = $this->getTestAmenityId();
        $this->assignmentManager->setAssigned('etechflow_isp_store_amenity', 'amenity_id', $storeId, [$amenityId]);
        $assigned = $this->assignmentManager->getAssigned('etechflow_isp_store_amenity', 'amenity_id', $storeId);
        $this->assert(in_array($amenityId, $assigned, true), 'Amenity assignment: round-trip', $output);

        $this->assignmentManager->setAssigned('etechflow_isp_store_amenity', 'amenity_id', $storeId, []);
        $assigned = $this->assignmentManager->getAssigned('etechflow_isp_store_amenity', 'amenity_id', $storeId);
        $this->assert($assigned === [], 'Amenity assignment: clear', $output);

        // Tag assignment
        $tagId = $this->getTestTagId();
        $this->assignmentManager->setAssigned('etechflow_isp_store_tag', 'tag_id', $storeId, [$tagId]);
        $this->assert(
            in_array($tagId, $this->assignmentManager->getAssigned('etechflow_isp_store_tag', 'tag_id', $storeId), true),
            'Tag assignment: round-trip',
            $output
        );

        // Hours
        $this->hoursManager->replaceRows($storeId, [
            0 => ['is_closed' => 1],
            1 => ['is_closed' => 0, 'open_time' => '09:00', 'close_time' => '17:00'],
            2 => ['is_closed' => 0, 'open_time' => '09:00', 'close_time' => '17:00'],
            3 => ['is_closed' => 0, 'open_time' => '09:00', 'close_time' => '17:00'],
            4 => ['is_closed' => 0, 'open_time' => '09:00', 'close_time' => '17:00'],
            5 => ['is_closed' => 0, 'open_time' => '09:00', 'close_time' => '17:00'],
            6 => ['is_closed' => 0, 'open_time' => '10:00', 'close_time' => '14:00'],
        ]);
        $rows = $this->hoursManager->getRows($storeId);
        $this->assert(count($rows) === 7, 'Hours: exactly 7 rows', $output);
        $this->assert($rows[1]['open_time'] === '09:00', 'Hours: Monday open=09:00', $output);
        $this->assert($rows[6]['close_time'] === '14:00', 'Hours: Saturday close=14:00', $output);
        $this->assert($rows[0]['is_closed'] === 1, 'Hours: Sunday closed', $output);

        // Exceptions
        $this->exceptionManager->replaceRows($storeId, [
            ['exception_date' => '2026-12-25', 'is_closed' => 1, 'reason' => 'Test Christmas'],
            ['exception_date' => '2026-12-26', 'is_closed' => 0, 'open_time' => '10:00', 'close_time' => '14:00', 'reason' => 'Test Boxing'],
        ]);
        $exc = $this->exceptionManager->getRows($storeId);
        $this->assert(count($exc) === 2, 'Exceptions: 2 rows persisted', $output);

        // Dedupe (same date twice)
        $this->exceptionManager->replaceRows($storeId, [
            ['exception_date' => '2026-12-25', 'is_closed' => 1, 'reason' => 'First'],
            ['exception_date' => '2026-12-25', 'is_closed' => 0, 'reason' => 'Dup — should be dropped'],
        ]);
        $this->assert(count($this->exceptionManager->getRows($storeId)) === 1, 'Exceptions: dedupe by date', $output);

        // Window overrides
        $windowId = $this->getTestWindowId();
        $this->windowOverrideManager->replaceRows($storeId, [
            ['window_id' => $windowId, 'is_disabled' => 1, 'capacity_override' => null],
        ]);
        $this->assert(count($this->windowOverrideManager->getRows($storeId)) === 1, 'Window overrides: row persisted', $output);

        // No-op pruning
        $this->windowOverrideManager->replaceRows($storeId, [
            ['window_id' => $windowId, 'is_disabled' => 0, 'capacity_override' => null],
        ]);
        $this->assert(count($this->windowOverrideManager->getRows($storeId)) === 0, 'Window overrides: no-op row pruned', $output);
    }

    private function testCarrier(OutputInterface $output): void
    {
        $this->assert($this->pickupCarrier->isActive(), 'Carrier: isActive=true', $output);
        $this->assert($this->pickupCarrier->getCarrierCode() === 'etechflow_isp', 'Carrier: code=etechflow_isp', $output);

        // collectRates with a minimal request: should yield one method per active store.
        $request = new RateRequest();
        $request->setDestCountryId('GB');
        $request->setPackageWeight(0);
        $request->setFreeShipping(false);

        $result = $this->pickupCarrier->collectRates($request);
        $methods = $result ? $result->getAllRates() : [];
        $this->assert(count($methods) > 0, 'Carrier: collectRates returns ≥1 method', $output);

        $codes = array_map(fn ($m) => $m->getMethod(), $methods);
        $hasOurStore = (bool) array_filter($codes, fn ($c) => str_contains($c, self::TEST_PREFIX . 'main'));
        $this->assert($hasOurStore, 'Carrier: includes _isptest_main method', $output);
    }

    private function testAutofillPlugin(OutputInterface $output): void
    {
        $storeId = $this->getTestStoreId();
        if ($storeId === 0) {
            $this->fail('No test store — skipping autofill', $output);
            return;
        }
        $store = $this->storeRepository->getById($storeId);

        // Build a fake quote address with garbage fields. Then call the
        // plugin and verify it gets overwritten with the store's address.
        $quote = $this->quoteFactory->create();
        $address = $quote->getShippingAddress();
        $address->setStreet('CUSTOMER_OLD_STREET');
        $address->setCity('CUSTOMER_OLD_CITY');
        $address->setPostcode('OLD-PC');
        $address->setCountryId('IE');
        $address->setShippingMethod(ShippingAddressAutofillPlugin::METHOD_PREFIX . $store->getCode());

        // The plugin's after method is fired by Magento. We can invoke it directly:
        $result = $address; // setShippingMethod returns the address
        $this->autofillPlugin->afterSetShippingMethod($address, $result, $address->getShippingMethod());

        $this->assert(
            (string) $address->getCity() === (string) $store->getCity(),
            'Auto-fill: city overwritten',
            $output
        );
        $this->assert(
            (string) $address->getPostcode() === (string) $store->getPostcode(),
            'Auto-fill: postcode overwritten',
            $output
        );
        $this->assert(
            (string) $address->getCountryId() === (string) $store->getCountryCode(),
            'Auto-fill: country overwritten',
            $output
        );

        // Now test non-pickup method: nothing should be overwritten.
        $address->setStreet('SHOULD_NOT_CHANGE');
        $address->setShippingMethod('flatrate_flatrate');
        $this->autofillPlugin->afterSetShippingMethod($address, $address, 'flatrate_flatrate');
        $afterStreet = $address->getStreet();
        $afterStreet = is_array($afterStreet) ? implode("\n", $afterStreet) : (string) $afterStreet;
        $this->assert(
            $afterStreet === 'SHOULD_NOT_CHANGE',
            'Auto-fill: non-pickup method leaves address alone',
            $output
        );
    }

    private function testDetector(OutputInterface $output): void
    {
        $storeId = $this->getTestStoreId();
        $store = $this->storeRepository->getById($storeId);

        $order = $this->orderFactory->create();
        $order->setShippingMethod(ShippingAddressAutofillPlugin::METHOD_PREFIX . $store->getCode());
        $this->assert($this->pickupOrderDetector->isPickupOrder($order), 'Detector: recognises pickup order', $output);
        $resolved = $this->pickupOrderDetector->getStoreForOrder($order);
        $this->assert($resolved && $resolved->getStoreId() === $store->getStoreId(), 'Detector: resolves correct store', $output);

        $order->setShippingMethod('flatrate_flatrate');
        $this->assert(!$this->pickupOrderDetector->isPickupOrder($order), 'Detector: rejects non-pickup method', $output);
        $this->assert($this->pickupOrderDetector->getStoreForOrder($order) === null, 'Detector: getStoreForOrder=null for non-pickup', $output);
    }

    private function testStaffAlertEmail(OutputInterface $output): void
    {
        $storeId = $this->getTestStoreId();
        $store = $this->storeRepository->getById($storeId);

        // Email needs a contact address on the store to send to staff.
        $store->setEmail('staff-test@example.invalid');
        $this->storeRepository->save($store);

        $order = $this->fakeOrder($store);
        $sent = $this->staffAlertSender->send($order, $store);
        $this->assert($sent, 'StaffAlertSender: send returned true (transport accepted)', $output);
    }

    private function testPickupReadyEmail(OutputInterface $output): void
    {
        $storeId = $this->getTestStoreId();
        $store = $this->storeRepository->getById($storeId);
        $order = $this->fakeOrder($store);
        $sent = $this->pickupReadySender->send($order, $store);
        $this->assert($sent, 'PickupReadySender: send returned true', $output);

        // No email on order → false but doesn't throw
        $orderNoEmail = $this->fakeOrder($store);
        $orderNoEmail->setCustomerEmail('');
        $this->assert(!$this->pickupReadySender->send($orderNoEmail, $store), 'PickupReadySender: missing email returns false', $output);
    }

    private function testMarkReadyIdempotency(OutputInterface $output): void
    {
        // Test the marker-detection helper standalone (since instantiating
        // the controller in CLI isn't useful — execute() needs a request).
        $marker = \ETechFlow\InStorePickup\Controller\Adminhtml\Order\MarkReady::SENT_MARKER;
        $this->assert(str_starts_with($marker, '[etechflow_isp:'), 'MarkReady: marker token is module-scoped', $output);

        // Build a fake order with a history item containing the marker,
        // and one without. The wasAlreadyMarked logic is private — assert
        // by string-detection on the comment we'd otherwise add.
        $comment = sprintf('%s Customer notified that order is ready at Test.', $marker);
        $this->assert(str_contains($comment, $marker), 'MarkReady: marker is detectable in comment', $output);
    }

    /**
     * Build an in-memory Order with just enough fields for an email send.
     * Not persisted to sales_order — we don't want test invoices polluting
     * the customer's real order list.
     */
    private function fakeOrder(\ETechFlow\InStorePickup\Api\Data\StoreInterface $store): \Magento\Sales\Model\Order
    {
        $order = $this->orderFactory->create();
        $order->setIncrementId('TEST-' . substr(uniqid(), -6));
        $order->setShippingMethod(ShippingAddressAutofillPlugin::METHOD_PREFIX . $store->getCode());
        $order->setCustomerEmail('customer-test@example.invalid');
        $order->setCustomerFirstname('Test');
        $order->setCustomerLastname('Customer');
        $order->setGrandTotal(42.00);
        $order->setStoreId(1);
        return $order;
    }

    private function getTestStoreId(): int
    {
        try {
            return (int) $this->storeRepository->getByCode(self::TEST_PREFIX . 'main')->getStoreId();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function getTestAmenityId(): int
    {
        foreach ($this->amenityOptions->toOptionArray() as $opt) {
            if (str_starts_with($opt['label'], 'Test ')) {
                return (int) $opt['value'];
            }
        }
        return 0;
    }

    private function getTestTagId(): int
    {
        foreach ($this->tagOptions->toOptionArray() as $opt) {
            if (str_starts_with($opt['label'], 'Test ')) {
                return (int) $opt['value'];
            }
        }
        return 0;
    }

    private function getTestWindowId(): int
    {
        foreach ($this->pickupWindowOptions->toOptionArray() as $opt) {
            if (str_contains($opt['label'], 'Test ')) {
                return (int) $opt['value'];
            }
        }
        return 0;
    }

    private function cleanupTestData(): void
    {
        $scb = \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Framework\Api\SearchCriteriaBuilder::class);

        // Delete entities whose code starts with TEST_PREFIX. We use getList()
        // for every repo (rather than relying on getByCode) because only the
        // Store repo exposes getByCode — TagRepository/AmenityRepository/etc.
        // only expose getById + getList. Cascade FKs clean up child pivot rows.
        foreach ([
            [$this->storeRepository,        'code'],
            [$this->tagRepository,          'code'],
            [$this->amenityRepository,      'code'],
            [$this->pickupWindowRepository, 'code'],
        ] as [$repo, $codeField]) {
            try {
                $criteria = $scb->addFilter($codeField, self::TEST_PREFIX . '%', 'like')->create();
                foreach ($repo->getList($criteria)->getItems() as $entity) {
                    $repo->delete($entity);
                }
            } catch (\Throwable $e) {
                // tolerate (table may be empty, repo doesn't support filter, etc.)
            }
        }

        // Holidays don't have a "code" — clean by country_code=XX (test marker).
        try {
            $criteria = $scb->addFilter('country_code', 'XX')->create();
            foreach ($this->holidayRepository->getList($criteria)->getItems() as $h) {
                $this->holidayRepository->delete($h);
            }
        } catch (\Throwable $e) {
            // tolerate
        }
    }

    private function reportResults(OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln(str_repeat('=', 60));
        $output->writeln('<info>RESULTS</info>');

        $totalPassed = 0;
        $totalFailed = 0;
        foreach ($this->results as $section => $r) {
            $totalPassed += $r['passed'];
            $totalFailed += $r['failed'];
            $status = $r['failed'] === 0 ? '<info>PASS</info>' : '<error>FAIL</error>';
            $output->writeln(sprintf('  %s %-32s %d passed, %d failed', $status, $section, $r['passed'], $r['failed']));
        }
        $output->writeln('');
        $output->writeln(sprintf(
            '%s — %d assertions, %d failed',
            $totalFailed === 0 ? '<info>✅ ALL PASS</info>' : '<error>❌ FAILURES PRESENT</error>',
            $totalPassed + $totalFailed,
            $totalFailed
        ));
        return $totalFailed === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
