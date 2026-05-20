<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\PickupWindow;

use ETechFlow\InStorePickup\Api\PickupWindowRepositoryInterface;
use ETechFlow\InStorePickup\Model\ResourceModel\PickupWindow\CollectionFactory as PickupWindowCollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;

class MassDelete extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::pickup_windows';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly PickupWindowCollectionFactory $collectionFactory,
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

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $deleted = 0;
            foreach ($collection as $window) {
                try {
                    $this->pickupWindowRepository->delete($window);
                    $deleted++;
                } catch (\Throwable $e) {
                    $this->logger->error('ETechFlow_InStorePickup: mass-delete pickup-window failed.', [
                        'window_id' => $window->getWindowId(),
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
            $this->messageManager->addSuccessMessage(__('%1 pickup window(s) deleted.', $deleted));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $redirect->setPath('*/*/index');
    }
}
