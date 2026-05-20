<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Api\Data;

/**
 * Service contract for a global holiday.
 *
 * Holidays apply to ALL stores by default. Per-store opt-out is recorded
 * in `etechflow_isp_store_holiday_exclusion` and managed via the Store
 * edit form's Holidays tab (Phase 5+).
 */
interface HolidayInterface
{
    public const HOLIDAY_ID    = 'holiday_id';
    public const NAME          = 'name';
    public const HOLIDAY_DATE  = 'holiday_date';
    public const IS_RECURRING  = 'is_recurring';
    public const IS_CLOSED     = 'is_closed';
    public const REDUCED_OPEN  = 'reduced_open';
    public const REDUCED_CLOSE = 'reduced_close';
    public const COUNTRY_CODE  = 'country_code';

    /** @return int|null */
    public function getHolidayId(): ?int;
    /** @param int|null $holidayId @return self */
    public function setHolidayId(?int $holidayId): self;

    /** @return string */
    public function getName(): string;
    /** @param string $name @return self */
    public function setName(string $name): self;

    /** @return string YYYY-MM-DD. */
    public function getHolidayDate(): string;
    /** @param string $date @return self */
    public function setHolidayDate(string $date): self;

    /** @return bool When true, applies annually on same MM-DD. */
    public function isRecurring(): bool;
    /** @param bool $isRecurring @return self */
    public function setIsRecurring(bool $isRecurring): self;

    /** @return bool When true, stores are closed all day. */
    public function isClosed(): bool;
    /** @param bool $isClosed @return self */
    public function setIsClosed(bool $isClosed): self;

    /** @return string|null HH:MM — only used when is_closed = false. */
    public function getReducedOpen(): ?string;
    /** @param string|null $time @return self */
    public function setReducedOpen(?string $time): self;

    /** @return string|null HH:MM */
    public function getReducedClose(): ?string;
    /** @param string|null $time @return self */
    public function setReducedClose(?string $time): self;

    /** @return string|null ISO country code if from a holiday seed (GB/US/...). NULL = custom. */
    public function getCountryCode(): ?string;
    /** @param string|null $code @return self */
    public function setCountryCode(?string $code): self;
}
