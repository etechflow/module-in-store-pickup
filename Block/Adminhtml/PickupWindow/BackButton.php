<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Block\Adminhtml\PickupWindow;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class BackButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        return [
            'label'      => (string) __('Back'),
            'on_click'   => sprintf('location.href = "%s";', $this->getUrl('etechflow_isp/pickupwindow/index')),
            'class'      => 'back',
            'sort_order' => 10,
        ];
    }
}
