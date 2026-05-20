<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Block\Adminhtml\Store;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class SaveAndContinueButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        return [
            'label'          => (string) __('Save and Continue Edit'),
            'class'          => 'save',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'saveAndContinueEdit']],
            ],
            'sort_order'     => 80,
        ];
    }
}
