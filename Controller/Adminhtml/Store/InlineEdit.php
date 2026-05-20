<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Store;

use ETechFlow\InStorePickup\Api\StoreRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Handles UI Component inline-edit submissions on the Stores grid.
 *
 * Lets the admin toggle is_active or rename a store from the grid
 * without opening the full edit form.
 */
class InlineEdit extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::stores';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly StoreRepositoryInterface $storeRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @return Json
     */
    public function execute()
    {
        /** @var Json $result */
        $result = $this->jsonFactory->create();
        $items = $this->getRequest()->getParam('items', []);
        $errors = [];

        if (!is_array($items) || empty($items)) {
            return $result->setData(['error' => true, 'messages' => [__('Nothing to update.')->render()]]);
        }

        foreach ($items as $storeId => $data) {
            try {
                $store = $this->storeRepository->getById((int) $storeId);
                $store->setData(array_merge($store->getData(), $data));
                $this->storeRepository->save($store);
            } catch (NoSuchEntityException $e) {
                $errors[] = (string) __('Store id %1 not found.', $storeId);
            } catch (\Throwable $e) {
                $errors[] = (string) __('Store id %1: %2', $storeId, $e->getMessage());
            }
        }

        return $result->setData([
            'messages' => $errors,
            'error'    => !empty($errors),
        ]);
    }
}
