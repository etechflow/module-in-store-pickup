<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Holiday;

use ETechFlow\InStorePickup\Api\HolidayRepositoryInterface;
use ETechFlow\InStorePickup\Model\ResourceModel\Holiday\CollectionFactory as HolidayCollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;

class MassDelete extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::holidays';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly HolidayCollectionFactory $collectionFactory,
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

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $deleted = 0;
            foreach ($collection as $holiday) {
                try {
                    $this->holidayRepository->delete($holiday);
                    $deleted++;
                } catch (\Throwable $e) {
                    $this->logger->error('ETechFlow_InStorePickup: mass-delete holiday failed.', [
                        'holiday_id' => $holiday->getHolidayId(),
                        'exception'  => $e->getMessage(),
                    ]);
                }
            }
            $this->messageManager->addSuccessMessage(__('%1 holiday(s) deleted.', $deleted));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $redirect->setPath('*/*/index');
    }
}
