<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Amenity;

use ETechFlow\InStorePickup\Api\AmenityRepositoryInterface;
use ETechFlow\InStorePickup\Model\ResourceModel\Amenity\CollectionFactory as AmenityCollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;

class MassDelete extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::amenities';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly AmenityCollectionFactory $collectionFactory,
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

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $deleted = 0;
            foreach ($collection as $amenity) {
                try {
                    $this->amenityRepository->delete($amenity);
                    $deleted++;
                } catch (\Throwable $e) {
                    $this->logger->error('ETechFlow_InStorePickup: mass-delete amenity failed.', [
                        'amenity_id' => $amenity->getAmenityId(),
                        'exception'  => $e->getMessage(),
                    ]);
                }
            }
            $this->messageManager->addSuccessMessage(__('%1 amenity(ies) deleted.', $deleted));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $redirect->setPath('*/*/index');
    }
}
