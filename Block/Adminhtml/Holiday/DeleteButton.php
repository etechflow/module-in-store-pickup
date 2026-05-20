<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Block\Adminhtml\Holiday;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $holidayId = $this->getHolidayId();
        if ($holidayId === null) {
            return [];
        }
        return [
            'label'      => (string) __('Delete Holiday'),
            'class'      => 'delete',
            'on_click'   => sprintf(
                'deleteConfirm("%s", "%s")',
                (string) __('Delete this holiday? Per-store opt-outs will be removed too.'),
                $this->getUrl('etechflow_isp/holiday/delete', ['holiday_id' => $holidayId])
            ),
            'sort_order' => 20,
        ];
    }
}
