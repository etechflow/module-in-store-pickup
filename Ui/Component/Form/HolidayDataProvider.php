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
            $this->loadedData[$holiday->getHolidayId()] = $holiday->getData();
        }

        $persisted = $this->dataPersistor->get('etechflow_isp_holiday');
        if (!empty($persisted)) {
            $holiday = $this->collection->getNewEmptyItem();
            $holiday->setData($persisted);
            $this->loadedData[$holiday->getHolidayId() ?? 0] = $holiday->getData();
            $this->dataPersistor->clear('etechflow_isp_holiday');
        }

        return $this->loadedData ?? [];
    }
}
