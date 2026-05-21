<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Amenity;

use ETechFlow\InStorePickup\Api\AmenityRepositoryInterface;
use ETechFlow\InStorePickup\Controller\Adminhtml\AjaxSaveResultTrait;
use ETechFlow\InStorePickup\Model\AmenityFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;

class Save extends Action implements HttpPostActionInterface
{
    use AjaxSaveResultTrait;

    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::amenities';

    private const DATA_PERSISTOR_KEY = 'etechflow_isp_amenity';

    public function __construct(
        Context $context,
        private readonly AmenityRepositoryInterface $amenityRepository,
        private readonly AmenityFactory $amenityFactory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly JsonFactory $ajaxSaveResultJsonFactory,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $data = $this->getRequest()->getPostValue();
        if (empty($data)) {
            return $this->respondRedirect('*/*/index');
        }

        $data = $this->normalisePayload($data);
        $amenityId = (int) ($data['amenity_id'] ?? 0);

        try {
            // Persist BEFORE validation — if anything throws, the form
            // rehydrates from this on the next page load.
            $this->dataPersistor->set(self::DATA_PERSISTOR_KEY, $data);

            $amenity = $amenityId > 0
                ? $this->amenityRepository->getById($amenityId)
                : $this->amenityFactory->create();

            if (empty($data['code'])) {
                $this->messageManager->addErrorMessage(__('Amenity code is required.'));
                return $this->respondRedirect('*/*/edit', ['amenity_id' => $amenityId], true);
            }
            if (empty($data['label'])) {
                $this->messageManager->addErrorMessage(__('Amenity label is required.'));
                return $this->respondRedirect('*/*/edit', ['amenity_id' => $amenityId], true);
            }

            $amenity->setData(array_merge($amenity->getData(), $data));
            $this->amenityRepository->save($amenity);

            $this->dataPersistor->clear(self::DATA_PERSISTOR_KEY);
            $this->messageManager->addSuccessMessage(__('Amenity saved: %1', $amenity->getLabel()));

            // Land on the edit form for the just-saved amenity so the
            // admin sees their data persisted.
            return $this->respondRedirect(
                '*/*/edit',
                ['amenity_id' => $amenity->getAmenityId(), '_current' => true]
            );
        } catch (\Throwable $e) {
            $this->logger->error('ETechFlow_InStorePickup: amenity save failed.', ['exception' => $e->getMessage()]);
            $this->messageManager->addErrorMessage(__('Could not save amenity: %1', $e->getMessage()));
            return $this->respondRedirect('*/*/edit', ['amenity_id' => $amenityId], true);
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
