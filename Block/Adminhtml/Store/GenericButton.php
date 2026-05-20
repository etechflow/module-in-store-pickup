<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Block\Adminhtml\Store;

use ETechFlow\InStorePickup\Api\StoreRepositoryInterface;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Base class for the form-action buttons.
 *
 * Lets the concrete button blocks (Save, Delete, Back, SaveAndContinue) share
 * the store-id lookup + URL helpers without copying boilerplate.
 */
class GenericButton
{
    public function __construct(
        protected readonly Context $context,
        protected readonly StoreRepositoryInterface $storeRepository
    ) {
    }

    /**
     * @return int|null
     */
    public function getStoreId(): ?int
    {
        try {
            $id = (int) $this->context->getRequest()->getParam('store_id');
            if ($id <= 0) {
                return null;
            }
            return $this->storeRepository->getById($id)->getStoreId();
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * @param string $route
     * @param array<string, mixed> $params
     * @return string
     */
    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
