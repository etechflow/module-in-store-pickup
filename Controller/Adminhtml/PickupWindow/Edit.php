<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\PickupWindow;

use ETechFlow\InStorePickup\Api\PickupWindowRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::pickup_windows';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly Registry $registry,
        private readonly PickupWindowRepositoryInterface $pickupWindowRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @return Page
     */
    public function execute()
    {
        $windowId = (int) $this->getRequest()->getParam('window_id');
        $title = __('New Pickup Window');

        if ($windowId > 0) {
            try {
                $window = $this->pickupWindowRepository->getById($windowId);
                $this->registry->register('etechflow_isp_current_pickup_window', $window);
                $title = __('Edit Pickup Window: %1', $window->getLabel());
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('Pickup window does not exist.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/index');
            }
        }

        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('ETechFlow_InStorePickup::pickup_windows');
        $resultPage->getConfig()->getTitle()->prepend($title);
        return $resultPage;
    }
}
