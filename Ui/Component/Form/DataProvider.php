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
            $row = $store->getData();
            $row['assigned_amenity_ids'] = $this->assignmentManager->getAssigned(
                'etechflow_isp_store_amenity',
                'amenity_id',
                $storeId
            );
            $row['assigned_tag_ids'] = $this->assignmentManager->getAssigned(
                'etechflow_isp_store_tag',
                'tag_id',
                $storeId
            );
            $hours = $this->hoursManager->getRows($storeId);
            foreach ($hours as $weekday => $hr) {
                $row['hours_' . $weekday . '_is_closed']  = $hr['is_closed'];
                $row['hours_' . $weekday . '_open_time']  = $hr['open_time'];
                $row['hours_' . $weekday . '_close_time'] = $hr['close_time'];
            }
            $row['exceptions']       = array_values($this->exceptionManager->getRows($storeId));
            $row['window_overrides'] = array_values($this->windowOverrideManager->getRows($storeId));
            $this->loadedData[$storeId] = $row;
        }

        // Re-hydrate from a failed save (so we don't lose what the customer typed).
        $persisted = $this->dataPersistor->get('etechflow_isp_store');
        if (!empty($persisted)) {
            $store = $this->collection->getNewEmptyItem();
            $store->setData($persisted);
            $this->loadedData[$store->getStoreId() ?? 0] = $store->getData();
            $this->dataPersistor->clear('etechflow_isp_store');
        }

        return $this->loadedData ?? [];
    }
}
