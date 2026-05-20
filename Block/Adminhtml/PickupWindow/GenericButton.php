<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Block\Adminhtml\PickupWindow;

use ETechFlow\InStorePickup\Api\PickupWindowRepositoryInterface;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Exception\NoSuchEntityException;

class GenericButton
{
    public function __construct(
        protected readonly Context $context,
        protected readonly PickupWindowRepositoryInterface $pickupWindowRepository
    ) {
    }

    /**
     * @return int|null
     */
    public function getWindowId(): ?int
    {
        try {
            $id = (int) $this->context->getRequest()->getParam('window_id');
            if ($id <= 0) {
                return null;
            }
            return $this->pickupWindowRepository->getById($id)->getWindowId();
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
