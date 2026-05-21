<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Ui\Component\Form;

use ETechFlow\InStorePickup\Model\ResourceModel\Tag\CollectionFactory as TagCollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * UI Component DataProvider for the Tag edit form.
 */
class TagDataProvider extends AbstractDataProvider
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $loadedData = null;

    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        TagCollectionFactory $collectionFactory,
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
        foreach ($items as $tag) {
            /** @var \ETechFlow\InStorePickup\Model\Tag $tag */
            $this->loadedData[$tag->getTagId()] = $this->castBooleans($tag->getData());
        }

        $persisted = $this->dataPersistor->get('etechflow_isp_tag');
        if (!empty($persisted)) {
            $tag = $this->collection->getNewEmptyItem();
            $tag->setData($persisted);
            $this->loadedData[$tag->getTagId() ?? 0] = $this->castBooleans($tag->getData());
            $this->dataPersistor->clear('etechflow_isp_tag');
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
