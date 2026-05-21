<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\Source;

use ETechFlow\InStorePickup\Model\ResourceModel\Tag\CollectionFactory as TagCollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Option source for tag multiselect on the Store form.
 *
 * Same shape/contract as {@see AmenityOptions} — see notes there.
 */
class TagOptions implements OptionSourceInterface
{
    public function __construct(
        private readonly TagCollectionFactory $collectionFactory
    ) {
    }

    /**
     * Values returned as strings — see {@see AmenityOptions::toOptionArray()}
     * for the strict-equality reason.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        $collection->setOrder('sort_order', 'ASC');

        $options = [];
        foreach ($collection as $tag) {
            /** @var \ETechFlow\InStorePickup\Model\Tag $tag */
            $options[] = [
                'value' => (string) $tag->getTagId(),
                'label' => (string) $tag->getLabel(),
            ];
        }
        return $options;
    }
}
