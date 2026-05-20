<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\PickupWindow;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\ResultFactory;

class NewAction extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::pickup_windows';

    /**
     * @return Forward
     */
    public function execute()
    {
        /** @var Forward $forward */
        $forward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);
        $forward->forward('edit');
        return $forward;
    }
}
