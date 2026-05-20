<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Api\Data;

/**
 * Service contract for a physical store / pickup location.
 *
 * Maps to a row in `etechflow_isp_store`. Each store represents one
 * real-world location where customers can collect orders.
 *
 * Related data (weekly hours, holiday exclusions, amenities, tags,
 * pickup-window overrides) is managed via separate repositories so
 * the Store entity itself stays simple.
 */
interface StoreInterface
{
    public const STORE_ID            = 'store_id';
    public const CODE                = 'code';
    public const NAME                = 'name';
    public const IS_ACTIVE           = 'is_active';
    public const DESCRIPTION         = 'description';
    public const PICKUP_INSTRUCTIONS = 'pickup_instructions';
    public const STREET              = 'street';
    public const CITY                = 'city';
    public const REGION              = 'region';
    public const POSTCODE            = 'postcode';
    public const COUNTRY_CODE        = 'country_code';
    public const LATITUDE            = 'latitude';
    public const LONGITUDE           = 'longitude';
    public const PHONE               = 'phone';
    public const EMAIL               = 'email';
    public const MANAGER_NAME        = 'manager_name';
    public const IMAGE               = 'image';
    public const SORT_ORDER          = 'sort_order';
    public const MSI_SOURCE_CODE     = 'msi_source_code';
    public const CREATED_AT          = 'created_at';
    public const UPDATED_AT          = 'updated_at';

    /**
     * @return int|null
     */
    public function getStoreId(): ?int;

    /**
     * @param int|null $storeId
     * @return self
     */
    public function setStoreId(?int $storeId): self;

    /**
     * @return string Unique short identifier — URL + CLI safe.
     */
    public function getCode(): string;

    /**
     * @param string $code
     * @return self
     */
    public function setCode(string $code): self;

    /**
     * @return string Human-readable store name.
     */
    public function getName(): string;

    /**
     * @param string $name
     * @return self
     */
    public function setName(string $name): self;

    /**
     * @return bool
     */
    public function isActive(): bool;

    /**
     * @param bool $isActive
     * @return self
     */
    public function setIsActive(bool $isActive): self;

    /**
     * @return string|null Rich text (HTML) for the storefront store page.
     */
    public function getDescription(): ?string;

    /**
     * @param string|null $description
     * @return self
     */
    public function setDescription(?string $description): self;

    /**
     * @return string|null Plain text shown on the pickup-ready email.
     */
    public function getPickupInstructions(): ?string;

    /**
     * @param string|null $instructions
     * @return self
     */
    public function setPickupInstructions(?string $instructions): self;

    /**
     * @return string|null Street address (line 1 + line 2 combined).
     */
    public function getStreet(): ?string;

    /**
     * @param string|null $street
     * @return self
     */
    public function setStreet(?string $street): self;

    /**
     * @return string|null
     */
    public function getCity(): ?string;

    /**
     * @param string|null $city
     * @return self
     */
    public function setCity(?string $city): self;

    /**
     * @return string|null Region / state / county.
     */
    public function getRegion(): ?string;

    /**
     * @param string|null $region
     * @return self
     */
    public function setRegion(?string $region): self;

    /**
     * @return string|null Postal / zip code.
     */
    public function getPostcode(): ?string;

    /**
     * @param string|null $postcode
     * @return self
     */
    public function setPostcode(?string $postcode): self;

    /**
     * @return string|null ISO 3166-1 alpha-2 country code.
     */
    public function getCountryCode(): ?string;

    /**
     * @param string|null $code
     * @return self
     */
    public function setCountryCode(?string $code): self;

    /**
     * @return float|null Latitude for map view.
     */
    public function getLatitude(): ?float;

    /**
     * @param float|null $latitude
     * @return self
     */
    public function setLatitude(?float $latitude): self;

    /**
     * @return float|null Longitude for map view.
     */
    public function getLongitude(): ?float;

    /**
     * @param float|null $longitude
     * @return self
     */
    public function setLongitude(?float $longitude): self;

    /**
     * @return string|null Public phone number.
     */
    public function getPhone(): ?string;

    /**
     * @param string|null $phone
     * @return self
     */
    public function setPhone(?string $phone): self;

    /**
     * @return string|null Staff-notification email target.
     */
    public function getEmail(): ?string;

    /**
     * @param string|null $email
     * @return self
     */
    public function setEmail(?string $email): self;

    /**
     * @return string|null
     */
    public function getManagerName(): ?string;

    /**
     * @param string|null $name
     * @return self
     */
    public function setManagerName(?string $name): self;

    /**
     * @return string|null Hero-image filename relative to pub/media/etechflow/isp/.
     */
    public function getImage(): ?string;

    /**
     * @param string|null $image
     * @return self
     */
    public function setImage(?string $image): self;

    /**
     * @return int
     */
    public function getSortOrder(): int;

    /**
     * @param int $sortOrder
     * @return self
     */
    public function setSortOrder(int $sortOrder): self;

    /**
     * @return string|null Optional Magento MSI source code linked to this store.
     */
    public function getMsiSourceCode(): ?string;

    /**
     * @param string|null $code
     * @return self
     */
    public function setMsiSourceCode(?string $code): self;

    /**
     * @return string|null ISO 8601 created_at timestamp.
     */
    public function getCreatedAt(): ?string;

    /**
     * @return string|null ISO 8601 updated_at timestamp.
     */
    public function getUpdatedAt(): ?string;
}
