<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Console\Command;

use ETechFlow\InStorePickup\Api\HolidayRepositoryInterface;
use ETechFlow\InStorePickup\Model\HolidayFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\State;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Module\Dir as ModuleDir;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Import a bundled JSON catalogue of public holidays for a country.
 *
 * Idempotent: skips any holiday already in the table with matching name
 * + date (so re-running is safe). Use --replace to wipe-and-reimport the
 * country's entries instead.
 *
 * Bundled catalogues live under app/code/ETechFlow/InStorePickup/etc/holidays/
 * as GB.json / IE.json / US.json. To add a country, drop a new JSON file
 * in that folder with the same schema — no code changes needed.
 */
class ImportHolidaysCommand extends Command
{
    public const NAME = 'etechflow:isp:import-holidays';

    public function __construct(
        private readonly State $appState,
        private readonly ModuleDir $moduleDir,
        private readonly FileDriver $fileDriver,
        private readonly HolidayRepositoryInterface $holidayRepository,
        private readonly HolidayFactory $holidayFactory,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName(self::NAME)
            ->setDescription('Import a bundled public-holiday catalogue (GB/IE/US/…) into ETechFlow ISP.')
            ->addOption('country', null, InputOption::VALUE_REQUIRED, 'Two-letter country code (GB, IE, US).', 'GB')
            ->addOption('replace', null, InputOption::VALUE_NONE, 'Wipe existing holidays for this country before importing.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be imported without writing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode('adminhtml');
        } catch (\Throwable $e) {
            // Area already set — fine
        }

        $country = strtoupper((string) $input->getOption('country'));
        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            $output->writeln('<error>Invalid --country: must be a 2-letter ISO code.</error>');
            return Command::FAILURE;
        }

        $path = $this->moduleDir->getDir('ETechFlow_InStorePickup', 'etc') . '/holidays/' . $country . '.json';
        if (!$this->fileDriver->isExists($path)) {
            $output->writeln(sprintf(
                '<error>No bundled catalogue for "%s". Looked for: %s</error>',
                $country,
                $path
            ));
            return Command::FAILURE;
        }

        $json = $this->fileDriver->fileGetContents($path);
        try {
            $catalogue = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $output->writeln('<error>Catalogue JSON is invalid: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $entries = $catalogue['holidays'] ?? [];
        if (empty($entries)) {
            $output->writeln('<comment>Catalogue has no holidays — nothing to do.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Loaded %d holidays for %s.</info>', count($entries), $country));
        if (!empty($catalogue['source'])) {
            $output->writeln('  Source: ' . $catalogue['source']);
        }

        $isDryRun = (bool) $input->getOption('dry-run');
        $replace  = (bool) $input->getOption('replace');

        if ($replace && !$isDryRun) {
            $removed = $this->wipeCountry($country);
            $output->writeln(sprintf('<comment>--replace: removed %d existing %s holidays.</comment>', $removed, $country));
        }

        $created = 0;
        $skipped = 0;
        foreach ($entries as $entry) {
            $name = (string) ($entry['name'] ?? '');
            $date = (string) ($entry['date'] ?? '');
            if ($name === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $output->writeln(sprintf('<comment>  Skipped malformed entry: %s</comment>', json_encode($entry)));
                $skipped++;
                continue;
            }

            if (!$replace && $this->exists($name, $date)) {
                $output->writeln(sprintf('  Skipped (already present): %s — %s', $date, $name));
                $skipped++;
                continue;
            }

            if ($isDryRun) {
                $output->writeln(sprintf('<info>  Would import:</info> %s — %s', $date, $name));
                continue;
            }

            $h = $this->holidayFactory->create();
            $h->setName($name)
              ->setHolidayDate($date)
              ->setIsRecurring(!empty($entry['is_recurring']))
              ->setIsClosed(true)
              ->setCountryCode($country);
            $this->holidayRepository->save($h);
            $created++;
            $output->writeln(sprintf('<info>  Imported:</info> %s — %s', $date, $name));
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '<info>Done. %d %s, %d skipped.</info>',
            $created,
            $isDryRun ? 'would-import' : 'imported',
            $skipped
        ));
        return Command::SUCCESS;
    }

    private function exists(string $name, string $date): bool
    {
        $criteria = $this->searchCriteriaBuilder
            ->addFilter('name', $name)
            ->addFilter('holiday_date', $date)
            ->create();
        $list = $this->holidayRepository->getList($criteria);
        return $list->getTotalCount() > 0;
    }

    private function wipeCountry(string $country): int
    {
        $criteria = $this->searchCriteriaBuilder->addFilter('country_code', $country)->create();
        $list = $this->holidayRepository->getList($criteria);
        $count = 0;
        foreach ($list->getItems() as $holiday) {
            $this->holidayRepository->delete($holiday);
            $count++;
        }
        return $count;
    }
}
