<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Block\Adminhtml\Store;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $storeId = $this->getStoreId();
        if ($storeId === null) {
            return [];
        }
        return [
            'label'      => (string) __('Delete Store'),
            'class'      => 'delete',
            'on_click'   => sprintf(
                'deleteConfirm("%s", "%s")',
                (string) __('Delete this store? Its hours, exceptions, amenities, and tag links will also be removed.'),
                $this->getUrl('etechflow_isp/store/delete', ['store_id' => $storeId])
            ),
            'sort_order' => 20,
        ];
    }
}
