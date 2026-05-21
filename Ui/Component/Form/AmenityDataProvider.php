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
            $this->loadedData[$amenity->getAmenityId()] = $this->castBooleans($amenity->getData());
        }

        $persisted = $this->dataPersistor->get('etechflow_isp_amenity');
        if (!empty($persisted)) {
            $amenity = $this->collection->getNewEmptyItem();
            $amenity->setData($persisted);
            $this->loadedData[$amenity->getAmenityId() ?? 0] = $this->castBooleans($amenity->getData());
            $this->dataPersistor->clear('etechflow_isp_amenity');
        }

        return $this->loadedData ?? [];
    }

    /**
     * v1.1.7 fix: MySQL returns smallint columns as PHP strings ("1", "0").
     * The form's <valueMap> entries declare integers via xsi:type="number".
     * Magento_Ui/js/form/element/single-checkbox uses strict equality —
     * "1" === 1 is false, so the toggle renders OFF even when the row's
     * is_active = 1. Cast all known boolean fields to int here so the
     * comparison works → display correct → click writes 1/0 properly.
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
