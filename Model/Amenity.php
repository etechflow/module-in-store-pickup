<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model;

use ETechFlow\InStorePickup\Api\Data\AmenityInterface;
use ETechFlow\InStorePickup\Model\ResourceModel\Amenity as AmenityResource;
use Magento\Framework\Model\AbstractModel;

class Amenity extends AbstractModel implements AmenityInterface
{
    /** @var string */
    protected $_eventPrefix = 'etechflow_isp_amenity';

    /** @var string */
    protected $_eventObject = 'amenity';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(AmenityResource::class);
    }

    public function getAmenityId(): ?int
    {
        $v = $this->getData(self::AMENITY_ID);
        return $v === null ? null : (int) $v;
    }

    public function setAmenityId(?int $amenityId): self
    {
        return $this->setData(self::AMENITY_ID, $amenityId);
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

    public function getIcon(): ?string
    {
        $v = $this->getData(self::ICON);
        return $v === null || $v === '' ? null : (string) $v;
    }

    public function setIcon(?string $icon): self
    {
        return $this->setData(self::ICON, $icon);
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
