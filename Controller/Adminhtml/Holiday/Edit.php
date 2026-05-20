<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Holiday;

use ETechFlow\InStorePickup\Api\HolidayRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::holidays';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly Registry $registry,
        private readonly HolidayRepositoryInterface $holidayRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @return Page
     */
    public function execute()
    {
        $holidayId = (int) $this->getRequest()->getParam('holiday_id');
        $title = __('New Holiday');

        if ($holidayId > 0) {
            try {
                $holiday = $this->holidayRepository->getById($holidayId);
                $this->registry->register('etechflow_isp_current_holiday', $holiday);
                $title = __('Edit Holiday: %1', $holiday->getName());
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('Holiday does not exist.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/index');
            }
        }

        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('ETechFlow_InStorePickup::holidays');
        $resultPage->getConfig()->getTitle()->prepend($title);
        return $resultPage;
    }
}
