<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Block\Adminhtml\Amenity;

use ETechFlow\InStorePickup\Api\AmenityRepositoryInterface;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Exception\NoSuchEntityException;

class GenericButton
{
    public function __construct(
        protected readonly Context $context,
        protected readonly AmenityRepositoryInterface $amenityRepository
    ) {
    }

    /**
     * @return int|null
     */
    public function getAmenityId(): ?int
    {
        try {
            $id = (int) $this->context->getRequest()->getParam('amenity_id');
            if ($id <= 0) {
                return null;
            }
            return $this->amenityRepository->getById($id)->getAmenityId();
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
