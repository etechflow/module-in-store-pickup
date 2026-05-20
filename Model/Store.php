<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model;

use ETechFlow\InStorePickup\Api\Data\StoreInterface;
use ETechFlow\InStorePickup\Model\ResourceModel\Store as StoreResource;
use Magento\Framework\Model\AbstractModel;

/**
 * Active-record style store entity. Wraps `etechflow_isp_store`.
 *
 * The typed getters/setters on top of getData()/setData() exist so
 * PHPStan + IDE autocomplete know the column types — Magento's
 * AbstractModel keeps the magic getter syntax working too.
 */
class Store extends AbstractModel implements StoreInterface
{
    /** @var string */
    protected $_eventPrefix = 'etechflow_isp_store';

    /** @var string */
    protected $_eventObject = 'store';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(StoreResource::class);
    }

    /**
     * @return int|null
     */
    public function getStoreId(): ?int
    {
        $value = $this->getData(self::STORE_ID);
        return $value === null ? null : (int) $value;
    }

    /**
     * @param int|null $storeId
     * @return self
     */
    public function setStoreId(?int $storeId): self
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return (string) $this->getData(self::CODE);
    }

    /**
     * @param string $code
     * @return self
     */
    public function setCode(string $code): self
    {
        return $this->setData(self::CODE, $code);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return (string) $this->getData(self::NAME);
    }

    /**
     * @param string $name
     * @return self
     */
    public function setName(string $name): self
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return (bool) $this->getData(self::IS_ACTIVE);
    }

    /**
     * @param bool $isActive
     * @return self
     */
    public function setIsActive(bool $isActive): self
    {
        return $this->setData(self::IS_ACTIVE, $isActive ? 1 : 0);
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        $v = $this->getData(self::DESCRIPTION);
        return $v === null || $v === '' ? null : (string) $v;
    }

    /**
     * @param string|null $description
     * @return self
     */
    public function setDescription(?string $description): self
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    /**
     * @return string|null
     */
    public function getPickupInstructions(): ?string
    {
        $v = $this->getData(self::PICKUP_INSTRUCTIONS);
        return $v === null || $v === '' ? null : (string) $v;
    }

    /**
     * @param string|null $instructions
     * @return self
     */
    public function setPickupInstructions(?string $instructions): self
    {
        return $this->setData(self::PICKUP_INSTRUCTIONS, $instructions);
    }

    /**
     * @return string|null
     */
    public function getStreet(): ?string
    {
        $v = $this->getData(self::STREET);
        return $v === null || $v === '' ? null : (string) $v;
    }

    public function setStreet(?string $street): self
    {
        return $this->setData(self::STREET, $street);
    }

    /**
     * @return string|null
     */
    public function getCity(): ?string
    {
        $v = $this->getData(self::CITY);
        return $v === null || $v === '' ? null : (string) $v;
    }

    public function setCity(?string $city): self
    {
        return $this->setData(self::CITY, $city);
    }

    /**
     * @return string|null
     */
    public function getRegion(): ?string
    {
        $v = $this->getData(self::REGION);
        return $v === null || $v === '' ? null : (string) $v;
    }

    public function setRegion(?string $region): self
    {
        return $this->setData(self::REGION, $region);
    }

    /**
     * @return string|null
     */
    public function getPostcode(): ?string
    {
        $v = $this->getData(self::POSTCODE);
        return $v === null || $v === '' ? null : (string) $v;
    }

    public function setPostcode(?string $postcode): self
    {
        return $this->setData(self::POSTCODE, $postcode);
    }

    /**
     * @return string|null
     */
    public function getCountryCode(): ?string
    {
        $v = $this->getData(self::COUNTRY_CODE);
        return $v === null || $v === '' ? null : (string) $v;
    }

    public function setCountryCode(?string $code): self
    {
        return $this->setData(self::COUNTRY_CODE, $code);
    }

    /**
     * @return float|null
     */
    public function getLatitude(): ?float
    {
        $v = $this->getData(self::LATITUDE);
        return $v === null || $v === '' ? null : (float) $v;
    }

    public function setLatitude(?float $latitude): self
    {
        return $this->setData(self::LATITUDE, $latitude);
    }

    /**
     * @return float|null
     */
    public function getLongitude(): ?float
    {
        $v = $this->getData(self::LONGITUDE);
        return $v === null || $v === '' ? null : (float) $v;
    }

    public function setLongitude(?float $longitude): self
    {
        return $this->setData(self::LONGITUDE, $longitude);
    }

    /**
     * @return string|null
     */
    public function getPhone(): ?string
    {
        $v = $this->getData(self::PHONE);
        return $v === null || $v === '' ? null : (string) $v;
    }

    public function setPhone(?string $phone): self
    {
        return $this->setData(self::PHONE, $phone);
    }

    /**
     * @return string|null
     */
    public function getEmail(): ?string
    {
        $v = $this->getData(self::EMAIL);
        return $v === null || $v === '' ? null : (string) $v;
    }

    public function setEmail(?string $email): self
    {
        return $this->setData(self::EMAIL, $email);
    }

    /**
     * @return string|null
     */
    public function getManagerName(): ?string
    {
        $v = $this->getData(self::MANAGER_NAME);
        return $v === null || $v === '' ? null : (string) $v;
    }

    public function setManagerName(?string $name): self
    {
        return $this->setData(self::MANAGER_NAME, $name);
    }

    /**
     * @return string|null
     */
    public function getImage(): ?string
    {
        $v = $this->getData(self::IMAGE);
        return $v === null || $v === '' ? null : (string) $v;
    }

    public function setImage(?string $image): self
    {
        return $this->setData(self::IMAGE, $image);
    }

    /**
     * @return int
     */
    public function getSortOrder(): int
    {
        return (int) $this->getData(self::SORT_ORDER);
    }

    public function setSortOrder(int $sortOrder): self
    {
        return $this->setData(self::SORT_ORDER, $sortOrder);
    }

    /**
     * @return string|null
     */
    public function getMsiSourceCode(): ?string
    {
        $v = $this->getData(self::MSI_SOURCE_CODE);
        return $v === null || $v === '' ? null : (string) $v;
    }

    public function setMsiSourceCode(?string $code): self
    {
        return $this->setData(self::MSI_SOURCE_CODE, $code);
    }

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string
    {
        $v = $this->getData(self::CREATED_AT);
        return $v === null ? null : (string) $v;
    }

    /**
     * @return string|null
     */
    public function getUpdatedAt(): ?string
    {
        $v = $this->getData(self::UPDATED_AT);
        return $v === null ? null : (string) $v;
    }
}
