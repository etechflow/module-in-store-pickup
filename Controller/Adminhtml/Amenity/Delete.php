<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Amenity;

use ETechFlow\InStorePickup\Api\AmenityRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::amenities';

    public function __construct(
        Context $context,
        private readonly AmenityRepositoryInterface $amenityRepository,
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
        $amenityId = (int) $this->getRequest()->getParam('amenity_id');

        if ($amenityId <= 0) {
            $this->messageManager->addErrorMessage(__('Missing amenity_id.'));
            return $redirect->setPath('*/*/index');
        }

        try {
            $this->amenityRepository->deleteById($amenityId);
            $this->messageManager->addSuccessMessage(__('Amenity deleted.'));
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('Amenity does not exist.'));
        } catch (\Throwable $e) {
            $this->logger->error('ETechFlow_InStorePickup: amenity delete failed.', ['exception' => $e->getMessage()]);
            $this->messageManager->addErrorMessage(__('Could not delete amenity: %1', $e->getMessage()));
        }

        return $redirect->setPath('*/*/index');
    }
}
