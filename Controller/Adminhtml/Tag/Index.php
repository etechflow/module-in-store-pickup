<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Tag;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Tag listing — eTechFlow → In-Store Pickup → Store Tags.
 */
class Index extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::tags';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @return Page
     */
    public function execute()
    {
        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('ETechFlow_InStorePickup::tags');
        $resultPage->getConfig()->getTitle()->prepend(__('In-Store Pickup — Store Tags'));
        return $resultPage;
    }
}
