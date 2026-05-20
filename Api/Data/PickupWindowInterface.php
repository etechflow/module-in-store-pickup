<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Api\Data;

/**
 * Service contract for a pickup-window slot template.
 *
 * Stores can use these as-is OR override per-store (capacity, disable)
 * via the `etechflow_isp_store_pickup_window` table.
 */
interface PickupWindowInterface
{
    public const WINDOW_ID  = 'window_id';
    public const CODE       = 'code';
    public const LABEL      = 'label';
    public const START_TIME = 'start_time';
    public const END_TIME   = 'end_time';
    public const CAPACITY   = 'capacity';
    public const SORT_ORDER = 'sort_order';
    public const IS_ACTIVE  = 'is_active';

    /** @return int|null */
    public function getWindowId(): ?int;
    /** @param int|null $windowId @return self */
    public function setWindowId(?int $windowId): self;

    /** @return string */
    public function getCode(): string;
    /** @param string $code @return self */
    public function setCode(string $code): self;

    /** @return string */
    public function getLabel(): string;
    /** @param string $label @return self */
    public function setLabel(string $label): self;

    /** @return string HH:MM (24h). */
    public function getStartTime(): string;
    /** @param string $startTime @return self */
    public function setStartTime(string $startTime): self;

    /** @return string HH:MM (24h). */
    public function getEndTime(): string;
    /** @param string $endTime @return self */
    public function setEndTime(string $endTime): self;

    /** @return int Max orders per window per store per day (0 = unlimited). */
    public function getCapacity(): int;
    /** @param int $capacity @return self */
    public function setCapacity(int $capacity): self;

    /** @return int */
    public function getSortOrder(): int;
    /** @param int $sortOrder @return self */
    public function setSortOrder(int $sortOrder): self;

    /** @return bool */
    public function isActive(): bool;
    /** @param bool $isActive @return self */
    public function setIsActive(bool $isActive): self;
}
