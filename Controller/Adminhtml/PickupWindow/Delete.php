<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\PickupWindow;

use ETechFlow\InStorePickup\Api\PickupWindowRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::pickup_windows';

    public function __construct(
        Context $context,
        private readonly PickupWindowRepositoryInterface $pickupWindowRepository,
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
        $windowId = (int) $this->getRequest()->getParam('window_id');

        if ($windowId <= 0) {
            $this->messageManager->addErrorMessage(__('Missing window_id.'));
            return $redirect->setPath('*/*/index');
        }

        try {
            $this->pickupWindowRepository->deleteById($windowId);
            $this->messageManager->addSuccessMessage(__('Pickup window deleted.'));
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('Pickup window does not exist.'));
        } catch (\Throwable $e) {
            $this->logger->error('ETechFlow_InStorePickup: pickup-window delete failed.', ['exception' => $e->getMessage()]);
            $this->messageManager->addErrorMessage(__('Could not delete pickup window: %1', $e->getMessage()));
        }

        return $redirect->setPath('*/*/index');
    }
}
