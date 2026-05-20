<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\Source;

use ETechFlow\InStorePickup\Model\ResourceModel\PickupWindow\CollectionFactory as PickupWindowCollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Option source for the pickup-window dropdown inside the window-override
 * dynamicRows fieldset on the Store form.
 *
 * Returns active windows ordered by sort_order. Labels include the time
 * range so admins can disambiguate two windows with the same label.
 */
class PickupWindowOptions implements OptionSourceInterface
{
    public function __construct(
        private readonly PickupWindowCollectionFactory $collectionFactory
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
        foreach ($collection as $window) {
            /** @var \ETechFlow\InStorePickup\Model\PickupWindow $window */
            $options[] = [
                'value' => (int) $window->getWindowId(),
                'label' => sprintf(
                    '%s (%s–%s)',
                    (string) $window->getLabel(),
                    (string) $window->getStartTime(),
                    (string) $window->getEndTime()
                ),
            ];
        }
        return $options;
    }
}
