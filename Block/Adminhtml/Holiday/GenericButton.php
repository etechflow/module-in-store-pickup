<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Block\Adminhtml\Holiday;

use ETechFlow\InStorePickup\Api\HolidayRepositoryInterface;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Exception\NoSuchEntityException;

class GenericButton
{
    public function __construct(
        protected readonly Context $context,
        protected readonly HolidayRepositoryInterface $holidayRepository
    ) {
    }

    /**
     * @return int|null
     */
    public function getHolidayId(): ?int
    {
        try {
            $id = (int) $this->context->getRequest()->getParam('holiday_id');
            if ($id <= 0) {
                return null;
            }
            return $this->holidayRepository->getById($id)->getHolidayId();
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
