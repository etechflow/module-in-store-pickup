<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Amenity;

use ETechFlow\InStorePickup\Api\AmenityRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::amenities';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly Registry $registry,
        private readonly AmenityRepositoryInterface $amenityRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @return Page
     */
    public function execute()
    {
        $amenityId = (int) $this->getRequest()->getParam('amenity_id');
        $title = __('New Amenity');

        if ($amenityId > 0) {
            try {
                $amenity = $this->amenityRepository->getById($amenityId);
                $this->registry->register('etechflow_isp_current_amenity', $amenity);
                $title = __('Edit Amenity: %1', $amenity->getLabel());
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('Amenity does not exist.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/index');
            }
        }

        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('ETechFlow_InStorePickup::amenities');
        $resultPage->getConfig()->getTitle()->prepend($title);
        return $resultPage;
    }
}
