<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Amenity;

use ETechFlow\InStorePickup\Api\AmenityRepositoryInterface;
use ETechFlow\InStorePickup\Model\AmenityFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Psr\Log\LoggerInterface;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::amenities';

    public function __construct(
        Context $context,
        private readonly AmenityRepositoryInterface $amenityRepository,
        private readonly AmenityFactory $amenityFactory,
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
        $data = $this->getRequest()->getPostValue();

        if (empty($data)) {
            return $redirect->setPath('*/*/index');
        }

        $data = $this->normalisePayload($data);

        try {
            $amenityId = (int) ($data['amenity_id'] ?? 0);
            $amenity = $amenityId > 0
                ? $this->amenityRepository->getById($amenityId)
                : $this->amenityFactory->create();

            if (empty($data['code'])) {
                $this->messageManager->addErrorMessage(__('Amenity code is required.'));
                return $redirect->setPath('*/*/edit', ['amenity_id' => $amenityId]);
            }
            if (empty($data['label'])) {
                $this->messageManager->addErrorMessage(__('Amenity label is required.'));
                return $redirect->setPath('*/*/edit', ['amenity_id' => $amenityId]);
            }

            $amenity->setData(array_merge($amenity->getData(), $data));
            $this->amenityRepository->save($amenity);

            $this->messageManager->addSuccessMessage(__('Amenity saved: %1', $amenity->getLabel()));

            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['amenity_id' => $amenity->getAmenityId()]);
            }
            return $redirect->setPath('*/*/index');
        } catch (\Throwable $e) {
            $this->logger->error('ETechFlow_InStorePickup: amenity save failed.', ['exception' => $e->getMessage()]);
            $this->messageManager->addErrorMessage(__('Could not save amenity: %1', $e->getMessage()));
            return $redirect->setPath('*/*/edit', ['amenity_id' => (int) ($data['amenity_id'] ?? 0)]);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalisePayload(array $data): array
    {
        foreach (['code', 'label', 'icon'] as $f) {
            if (isset($data[$f]) && is_string($data[$f])) {
                $data[$f] = trim($data[$f]);
            }
        }
        $data['is_active'] = !empty($data['is_active']) ? 1 : 0;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        return $data;
    }
}
