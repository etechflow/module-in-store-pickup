<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\Source;

use ETechFlow\InStorePickup\Model\ResourceModel\Amenity\CollectionFactory as AmenityCollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Option source for amenity multiselect on the Store form.
 *
 * Returns all active amenities ordered by sort_order. Inactive amenities
 * are excluded so admins can't accidentally assign retired ones — but
 * existing pivot rows referring to inactive amenities remain in DB.
 */
class AmenityOptions implements OptionSourceInterface
{
    public function __construct(
        private readonly AmenityCollectionFactory $collectionFactory
    ) {
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function toOptionArray(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        $collection->setOrder('sort_order', 'ASC');

        $options = [];
        foreach ($collection as $amenity) {
            /** @var \ETechFlow\InStorePickup\Model\Amenity $amenity */
            $options[] = [
                'value' => (int) $amenity->getAmenityId(),
                'label' => (string) $amenity->getLabel(),
            ];
        }
        return $options;
    }
}
