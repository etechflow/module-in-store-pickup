<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Store;

use ETechFlow\InStorePickup\Api\StoreRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Edit existing store OR new-blank form.
 *
 * Forwarded to from both NewAction (no store_id) and the listing row-click
 * (with store_id). The shared form layout handles both cases via the
 * DataProvider's `getData()` returning an empty array on new.
 */
class Edit extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::stores';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly Registry $registry,
        private readonly StoreRepositoryInterface $storeRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @return Page
     */
    public function execute()
    {
        $storeId = (int) $this->getRequest()->getParam('store_id');
        $title   = __('New Store');

        if ($storeId > 0) {
            try {
                $store = $this->storeRepository->getById($storeId);
                $this->registry->register('etechflow_isp_current_store', $store);
                $title = __('Edit Store: %1', $store->getName());
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('Store does not exist.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/index');
            }
        }

        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('ETechFlow_InStorePickup::stores');
        $resultPage->getConfig()->getTitle()->prepend($title);
        return $resultPage;
    }
}
