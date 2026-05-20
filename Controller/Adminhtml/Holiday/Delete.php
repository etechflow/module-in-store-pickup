<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Holiday;

use ETechFlow\InStorePickup\Api\HolidayRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::holidays';

    public function __construct(
        Context $context,
        private readonly HolidayRepositoryInterface $holidayRepository,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute()
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();
        $holidayId = (int) $this->getRequest()->getParam('holiday_id');

        if ($holidayId <= 0) {
            $this->messageManager->addErrorMessage(__('Missing holiday_id.'));
            return $redirect->setPath('*/*/index');
        }

        try {
            $this->holidayRepository->deleteById($holidayId);
            $this->messageManager->addSuccessMessage(__('Holiday deleted.'));
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('Holiday does not exist.'));
        } catch (\Throwable $e) {
            $this->logger->error('ETechFlow_InStorePickup: holiday delete failed.', ['exception' => $e->getMessage()]);
            $this->messageManager->addErrorMessage(__('Could not delete holiday: %1', $e->getMessage()));
        }

        return $redirect->setPath('*/*/index');
    }
}
