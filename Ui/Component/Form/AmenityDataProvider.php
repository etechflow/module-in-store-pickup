<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Ui\Component\Form;

use ETechFlow\InStorePickup\Model\ResourceModel\Amenity\CollectionFactory as AmenityCollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class AmenityDataProvider extends AbstractDataProvider
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $loadedData = null;

    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        AmenityCollectionFactory $collectionFactory,
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
        foreach ($items as $amenity) {
            /** @var \ETechFlow\InStorePickup\Model\Amenity $amenity */
            $this->loadedData[$amenity->getAmenityId()] = $amenity->getData();
        }

        $persisted = $this->dataPersistor->get('etechflow_isp_amenity');
        if (!empty($persisted)) {
            $amenity = $this->collection->getNewEmptyItem();
            $amenity->setData($persisted);
            $this->loadedData[$amenity->getAmenityId() ?? 0] = $amenity->getData();
            $this->dataPersistor->clear('etechflow_isp_amenity');
        }

        return $this->loadedData ?? [];
    }
}
