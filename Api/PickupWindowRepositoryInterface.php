<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Api;

use ETechFlow\InStorePickup\Api\Data\PickupWindowInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface PickupWindowRepositoryInterface
{
    /**
     * @param PickupWindowInterface $window
     * @return PickupWindowInterface
     * @throws CouldNotSaveException
     */
    public function save(PickupWindowInterface $window): PickupWindowInterface;

    /**
     * @param int $windowId
     * @return PickupWindowInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $windowId): PickupWindowInterface;

    /**
     * @param PickupWindowInterface $window
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(PickupWindowInterface $window): bool;

    /**
     * @param int $windowId
     * @return bool
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $windowId): bool;

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;
}
