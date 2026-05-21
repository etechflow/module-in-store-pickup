<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Ui\Component\Form;

use ETechFlow\InStorePickup\Model\ResourceModel\Store\CollectionFactory as StoreCollectionFactory;
use ETechFlow\InStorePickup\Model\Store\AssignmentManager;
use ETechFlow\InStorePickup\Model\Store\ExceptionManager;
use ETechFlow\InStorePickup\Model\Store\HoursManager;
use ETechFlow\InStorePickup\Model\Store\WindowOverrideManager;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * UI Component DataProvider for the Store edit form.
 *
 * Returns the loaded store data keyed by store_id. On a "new" form (no
 * store_id), returns the persisted form data from a failed save (so the
 * customer's typing isn't lost) OR an empty array.
 */
class DataProvider extends AbstractDataProvider
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $loadedData = null;

    /**
     * @param string                  $name
     * @param string                  $primaryFieldName
     * @param string                  $requestFieldName
     * @param StoreCollectionFactory  $collectionFactory
     * @param DataPersistorInterface  $dataPersistor
     * @param array                   $meta
     * @param array                   $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        StoreCollectionFactory $collectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly AssignmentManager $assignmentManager,
        private readonly HoursManager $hoursManager,
        private readonly ExceptionManager $exceptionManager,
        private readonly WindowOverrideManager $windowOverrideManager,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }

        $items = $this->collection->getItems();
        foreach ($items as $store) {
            /** @var \ETechFlow\InStorePickup\Model\Store $store */
            $storeId = (int) $store->getStoreId();
            $row = $this->castBooleans($store->getData());
            // Cast to string arrays — the multiselect compares against the
            // option values ([[AmenityOptions]] / [[TagOptions]] also strings)
            // with strict equality; int 1 vs string "1" leaves previously
            // saved selections unhighlighted on reload.
            $row['assigned_amenity_ids'] = array_map('strval', $this->assignmentManager->getAssigned(
                'etechflow_isp_store_amenity',
                'amenity_id',
                $storeId
            ));
            $row['assigned_tag_ids'] = array_map('strval', $this->assignmentManager->getAssigned(
                'etechflow_isp_store_tag',
                'tag_id',
                $storeId
            ));
            $hours = $this->hoursManager->getRows($storeId);
            foreach ($hours as $weekday => $hr) {
                // HoursManager.getRows() already casts is_closed to int — but be
                // defensive here in case that contract ever drifts.
                $row['hours_' . $weekday . '_is_closed']  = (int) $hr['is_closed'];
                $row['hours_' . $weekday . '_open_time']  = $hr['open_time'];
                $row['hours_' . $weekday . '_close_time'] = $hr['close_time'];
            }
            // v1.1.11 fix: dynamicRows expects its rows wrapped under its
            // own dataScope name — NOT as a flat array — that's how the
            // form's record-template adapter binds existing rows to its
            // internal records observable. Each row also needs a unique
            // `record_id` or the component renders nothing on form load,
            // even though the data is present.
            $row['exceptions'] = [
                'exceptions' => $this->prepareDynamicRows(
                    $this->exceptionManager->getRows($storeId),
                    'is_closed'
                ),
            ];
            $row['window_overrides'] = [
                'window_overrides' => $this->prepareDynamicRows(
                    $this->windowOverrideManager->getRows($storeId),
                    'is_disabled'
                ),
            ];
            $this->loadedData[$storeId] = $row;
        }

        // Re-hydrate from a failed save (so we don't lose what the customer typed).
        $persisted = $this->dataPersistor->get('etechflow_isp_store');
        if (!empty($persisted)) {
            $store = $this->collection->getNewEmptyItem();
            $store->setData($persisted);
            $this->loadedData[$store->getStoreId() ?? 0] = $this->castBooleans($store->getData());
            $this->dataPersistor->clear('etechflow_isp_store');
        }

        return $this->loadedData ?? [];
    }

    /**
     * v1.1.7 fix: MySQL returns smallint columns as PHP strings ("1", "0").
     * The form's `<valueMap>` entries declare integers via `xsi:type="number"`.
     * Magento's `Magento_Ui/js/form/element/single-checkbox` compares with
     * strict equality — `"1" === 1` is false — so the toggle renders OFF
     * even when the row's `is_active = 1`. Cast all known boolean fields
     * here so the comparison works → display correct → click writes 1/0.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function castBooleans(array $row): array
    {
        foreach (['is_active'] as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== null) {
                $row[$field] = (int) $row[$field];
            }
        }
        return $row;
    }

    /**
     * Shape rows for a dynamicRows component:
     *   - Reindex with `record_id` (component requires a unique row key
     *     or it renders nothing — data is loaded but invisible)
     *   - Cast the boolean column to int 0/1 to match the Yes/No
     *     (or Open/Closed) select valueMap on that field
     *
     * @param array<int, array<string, mixed>> $rows
     * @param string                           $boolField
     * @return array<int, array<string, mixed>>
     */
    private function prepareDynamicRows(array $rows, string $boolField): array
    {
        $rows = array_values($rows);
        foreach ($rows as $i => $row) {
            $row[$boolField] = (int) ($row[$boolField] ?? 0);
            $row['record_id'] = $i;
            $rows[$i] = $row;
        }
        return $rows;
    }
}
