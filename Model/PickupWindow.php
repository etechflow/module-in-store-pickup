<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model;

use ETechFlow\InStorePickup\Api\Data\PickupWindowInterface;
use ETechFlow\InStorePickup\Model\ResourceModel\PickupWindow as PickupWindowResource;
use Magento\Framework\Model\AbstractModel;

class PickupWindow extends AbstractModel implements PickupWindowInterface
{
    /** @var string */
    protected $_eventPrefix = 'etechflow_isp_pickup_window';

    /** @var string */
    protected $_eventObject = 'pickup_window';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(PickupWindowResource::class);
    }

    public function getWindowId(): ?int
    {
        $v = $this->getData(self::WINDOW_ID);
        return $v === null ? null : (int) $v;
    }

    public function setWindowId(?int $windowId): self
    {
        return $this->setData(self::WINDOW_ID, $windowId);
    }

    public function getCode(): string
    {
        return (string) $this->getData(self::CODE);
    }

    public function setCode(string $code): self
    {
        return $this->setData(self::CODE, $code);
    }

    public function getLabel(): string
    {
        return (string) $this->getData(self::LABEL);
    }

    public function setLabel(string $label): self
    {
        return $this->setData(self::LABEL, $label);
    }

    public function getStartTime(): string
    {
        return (string) $this->getData(self::START_TIME);
    }

    public function setStartTime(string $startTime): self
    {
        return $this->setData(self::START_TIME, $startTime);
    }

    public function getEndTime(): string
    {
        return (string) $this->getData(self::END_TIME);
    }

    public function setEndTime(string $endTime): self
    {
        return $this->setData(self::END_TIME, $endTime);
    }

    public function getCapacity(): int
    {
        return (int) $this->getData(self::CAPACITY);
    }

    public function setCapacity(int $capacity): self
    {
        return $this->setData(self::CAPACITY, $capacity);
    }

    public function getSortOrder(): int
    {
        return (int) $this->getData(self::SORT_ORDER);
    }

    public function setSortOrder(int $sortOrder): self
    {
        return $this->setData(self::SORT_ORDER, $sortOrder);
    }

    public function isActive(): bool
    {
        return (bool) $this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): self
    {
        return $this->setData(self::IS_ACTIVE, $isActive ? 1 : 0);
    }
}
