<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Store;

use ETechFlow\InStorePickup\Api\StoreRepositoryInterface;
use ETechFlow\InStorePickup\Model\ResourceModel\Store\CollectionFactory as StoreCollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;

/**
 * Mass-disable selected stores from the listing.
 */
class MassDisable extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::stores';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly StoreCollectionFactory $collectionFactory,
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

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $count = 0;
            foreach ($collection as $store) {
                if ($store->isActive()) {
                    $store->setIsActive(false);
                    try {
                        $this->storeRepository->save($store);
                        $count++;
                    } catch (\Throwable $e) {
                        $this->logger->error('ETechFlow_InStorePickup: mass-disable row failed.', [
                            'store_id'  => $store->getStoreId(),
                            'exception' => $e->getMessage(),
                        ]);
                    }
                }
            }
            $this->messageManager->addSuccessMessage(__('%1 store(s) disabled.', $count));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $redirect->setPath('*/*/index');
    }
}
