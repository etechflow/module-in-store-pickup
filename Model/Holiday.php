<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model;

use ETechFlow\InStorePickup\Api\Data\HolidayInterface;
use ETechFlow\InStorePickup\Model\ResourceModel\Holiday as HolidayResource;
use Magento\Framework\Model\AbstractModel;

class Holiday extends AbstractModel implements HolidayInterface
{
    /** @var string */
    protected $_eventPrefix = 'etechflow_isp_holiday';

    /** @var string */
    protected $_eventObject = 'holiday';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(HolidayResource::class);
    }

    public function getHolidayId(): ?int
    {
        $v = $this->getData(self::HOLIDAY_ID);
        return $v === null ? null : (int) $v;
    }

    public function setHolidayId(?int $holidayId): self
    {
        return $this->setData(self::HOLIDAY_ID, $holidayId);
    }

    public function getName(): string
    {
        return (string) $this->getData(self::NAME);
    }

    public function setName(string $name): self
    {
        return $this->setData(self::NAME, $name);
    }

    public function getHolidayDate(): string
    {
        return (string) $this->getData(self::HOLIDAY_DATE);
    }

    public function setHolidayDate(string $date): self
    {
        return $this->setData(self::HOLIDAY_DATE, $date);
    }

    public function isRecurring(): bool
    {
        return (bool) $this->getData(self::IS_RECURRING);
    }

    public function setIsRecurring(bool $isRecurring): self
    {
        return $this->setData(self::IS_RECURRING, $isRecurring ? 1 : 0);
    }

    public function isClosed(): bool
    {
        return (bool) $this->getData(self::IS_CLOSED);
    }

    public function setIsClosed(bool $isClosed): self
    {
        return $this->setData(self::IS_CLOSED, $isClosed ? 1 : 0);
    }

    public function getReducedOpen(): ?string
    {
        $v = $this->getData(self::REDUCED_OPEN);
        return $v === null || $v === '' ? null : (string) $v;
    }

    public function setReducedOpen(?string $time): self
    {
        return $this->setData(self::REDUCED_OPEN, $time);
    }

    public function getReducedClose(): ?string
    {
        $v = $this->getData(self::REDUCED_CLOSE);
        return $v === null || $v === '' ? null : (string) $v;
    }

    public function setReducedClose(?string $time): self
    {
        return $this->setData(self::REDUCED_CLOSE, $time);
    }

    public function getCountryCode(): ?string
    {
        $v = $this->getData(self::COUNTRY_CODE);
        return $v === null || $v === '' ? null : (string) $v;
    }

    public function setCountryCode(?string $code): self
    {
        return $this->setData(self::COUNTRY_CODE, $code);
    }
}
