<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Ui\Component\Form;

use ETechFlow\InStorePickup\Model\ResourceModel\Holiday\CollectionFactory as HolidayCollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class HolidayDataProvider extends AbstractDataProvider
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $loadedData = null;

    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        HolidayCollectionFactory $collectionFactory,
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
        foreach ($items as $holiday) {
            /** @var \ETechFlow\InStorePickup\Model\Holiday $holiday */
            $this->loadedData[$holiday->getHolidayId()] = $this->castBooleans($holiday->getData());
        }

        $persisted = $this->dataPersistor->get('etechflow_isp_holiday');
        if (!empty($persisted)) {
            $holiday = $this->collection->getNewEmptyItem();
            $holiday->setData($persisted);
            $this->loadedData[$holiday->getHolidayId() ?? 0] = $this->castBooleans($holiday->getData());
            $this->dataPersistor->clear('etechflow_isp_holiday');
        }

        return $this->loadedData ?? [];
    }

    /**
     * v1.1.7 fix — see AmenityDataProvider for the full explanation.
     * Magento UI Component toggles compare with strict equality against
     * a valueMap of integers; MySQL returns smallint columns as strings;
     * cast here so the toggles render the right state.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function castBooleans(array $row): array
    {
        foreach (['is_closed', 'is_recurring'] as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== null) {
                $row[$field] = (int) $row[$field];
            }
        }
        return $row;
    }
}
