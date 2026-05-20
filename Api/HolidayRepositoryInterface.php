<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Api;

use ETechFlow\InStorePickup\Api\Data\HolidayInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface HolidayRepositoryInterface
{
    /**
     * @param HolidayInterface $holiday
     * @return HolidayInterface
     * @throws CouldNotSaveException
     */
    public function save(HolidayInterface $holiday): HolidayInterface;

    /**
     * @param int $holidayId
     * @return HolidayInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $holidayId): HolidayInterface;

    /**
     * @param HolidayInterface $holiday
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(HolidayInterface $holiday): bool;

    /**
     * @param int $holidayId
     * @return bool
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $holidayId): bool;

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;
}
