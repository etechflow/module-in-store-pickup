<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Api;

use ETechFlow\InStorePickup\Api\Data\AmenityInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface AmenityRepositoryInterface
{
    /**
     * @param AmenityInterface $amenity
     * @return AmenityInterface
     * @throws CouldNotSaveException
     */
    public function save(AmenityInterface $amenity): AmenityInterface;

    /**
     * @param int $amenityId
     * @return AmenityInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $amenityId): AmenityInterface;

    /**
     * @param AmenityInterface $amenity
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(AmenityInterface $amenity): bool;

    /**
     * @param int $amenityId
     * @return bool
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $amenityId): bool;

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;
}
