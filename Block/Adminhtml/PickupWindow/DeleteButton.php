<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Block\Adminhtml\PickupWindow;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $windowId = $this->getWindowId();
        if ($windowId === null) {
            return [];
        }
        return [
            'label'      => (string) __('Delete Pickup Window'),
            'class'      => 'delete',
            'on_click'   => sprintf(
                'deleteConfirm("%s", "%s")',
                (string) __('Delete this pickup window? Store-window overrides will be removed too.'),
                $this->getUrl('etechflow_isp/pickupwindow/delete', ['window_id' => $windowId])
            ),
            'sort_order' => 20,
        ];
    }
}
