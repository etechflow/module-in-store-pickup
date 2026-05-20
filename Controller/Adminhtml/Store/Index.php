<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Store;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Stores listing page — entry point for the eTechFlow → In-Store Pickup → Stores admin.
 */
class Index extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::stores';

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
        $resultPage->setActiveMenu('ETechFlow_InStorePickup::stores');
        $resultPage->getConfig()->getTitle()->prepend(__('In-Store Pickup — Stores'));
        return $resultPage;
    }
}
