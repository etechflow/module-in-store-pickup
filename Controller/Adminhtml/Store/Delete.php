<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Store;

use ETechFlow\InStorePickup\Api\StoreRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Delete a single store. Cascades automatically to hours / exceptions
 * / amenity links / tag links / pickup-window overrides via FK ON DELETE.
 */
class Delete extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::stores';

    public function __construct(
        Context $context,
        private readonly StoreRepositoryInterface $storeRepository,
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
        $storeId = (int) $this->getRequest()->getParam('store_id');

        if ($storeId <= 0) {
            $this->messageManager->addErrorMessage(__('Missing store_id.'));
            return $redirect->setPath('*/*/index');
        }

        try {
            $this->storeRepository->deleteById($storeId);
            $this->messageManager->addSuccessMessage(__('Store deleted.'));
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('Store does not exist.'));
        } catch (\Throwable $e) {
            $this->logger->error('ETechFlow_InStorePickup: store delete failed.', ['exception' => $e->getMessage()]);
            $this->messageManager->addErrorMessage(__('Could not delete store: %1', $e->getMessage()));
        }

        return $redirect->setPath('*/*/index');
    }
}
