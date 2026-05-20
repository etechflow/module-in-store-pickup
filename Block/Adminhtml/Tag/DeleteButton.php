<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Block\Adminhtml\Tag;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $tagId = $this->getTagId();
        if ($tagId === null) {
            return [];
        }
        return [
            'label'      => (string) __('Delete Tag'),
            'class'      => 'delete',
            'on_click'   => sprintf(
                'deleteConfirm("%s", "%s")',
                (string) __('Delete this tag? Store-tag assignments will be removed too.'),
                $this->getUrl('etechflow_isp/tag/delete', ['tag_id' => $tagId])
            ),
            'sort_order' => 20,
        ];
    }
}
