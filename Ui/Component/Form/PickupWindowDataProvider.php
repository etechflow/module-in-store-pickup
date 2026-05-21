<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Ui\Component\Form;

use ETechFlow\InStorePickup\Model\ResourceModel\PickupWindow\CollectionFactory as PickupWindowCollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class PickupWindowDataProvider extends AbstractDataProvider
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $loadedData = null;

    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        PickupWindowCollectionFactory $collectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
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
        foreach ($items as $window) {
            /** @var \ETechFlow\InStorePickup\Model\PickupWindow $window */
            $this->loadedData[$window->getWindowId()] = $this->castBooleans($window->getData());
        }

        $persisted = $this->dataPersistor->get('etechflow_isp_pickup_window');
        if (!empty($persisted)) {
            $window = $this->collection->getNewEmptyItem();
            $window->setData($persisted);
            $this->loadedData[$window->getWindowId() ?? 0] = $this->castBooleans($window->getData());
            $this->dataPersistor->clear('etechflow_isp_pickup_window');
        }

        return $this->loadedData ?? [];
    }

    /**
     * v1.1.7 fix — see AmenityDataProvider for full explanation.
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
}
