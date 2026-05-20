<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Block\Adminhtml\Amenity;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $amenityId = $this->getAmenityId();
        if ($amenityId === null) {
            return [];
        }
        return [
            'label'      => (string) __('Delete Amenity'),
            'class'      => 'delete',
            'on_click'   => sprintf(
                'deleteConfirm("%s", "%s")',
                (string) __('Delete this amenity? Store-amenity assignments will be removed too.'),
                $this->getUrl('etechflow_isp/amenity/delete', ['amenity_id' => $amenityId])
            ),
            'sort_order' => 20,
        ];
    }
}
