<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Api\Data;

/**
 * Service contract for a store amenity (Parking, Coffee, Wheelchair, etc.).
 *
 * Maps to a row in `etechflow_isp_amenity`. Per-store amenity assignment
 * happens through the `etechflow_isp_store_amenity` link table (managed
 * via the Store edit form's Amenities tab in v1.1).
 */
interface AmenityInterface
{
    public const AMENITY_ID = 'amenity_id';
    public const CODE       = 'code';
    public const LABEL      = 'label';
    public const ICON       = 'icon';
    public const SORT_ORDER = 'sort_order';
    public const IS_ACTIVE  = 'is_active';

    /** @return int|null */
    public function getAmenityId(): ?int;
    /**
     * @param int|null $amenityId
     * @return self
     */
    public function setAmenityId(?int $amenityId): self;

    /** @return string */
    public function getCode(): string;
    /**
     * @param string $code
     * @return self
     */
    public function setCode(string $code): self;

    /** @return string */
    public function getLabel(): string;
    /**
     * @param string $label
     * @return self
     */
    public function setLabel(string $label): self;

    /** @return string|null Tabler icon name, or null for a generic dot. */
    public function getIcon(): ?string;
    /**
     * @param string|null $icon
     * @return self
     */
    public function setIcon(?string $icon): self;

    /** @return int */
    public function getSortOrder(): int;
    /**
     * @param int $sortOrder
     * @return self
     */
    public function setSortOrder(int $sortOrder): self;

    /** @return bool */
    public function isActive(): bool;
    /**
     * @param bool $isActive
     * @return self
     */
    public function setIsActive(bool $isActive): self;
}
