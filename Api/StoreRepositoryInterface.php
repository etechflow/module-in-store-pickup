<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Api;

use ETechFlow\InStorePickup\Api\Data\StoreInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Service contract repository for store / pickup location entities.
 *
 * Standard Magento service-contract shape with one extra convenience
 * method (`getAllActive`) — admin grids + the carrier load that often
 * enough that paying SearchCriteria roundtrips per call is wasteful.
 */
interface StoreRepositoryInterface
{
    /**
     * Persist a store record. Returns the saved entity with `store_id` populated on insert.
     *
     * @param StoreInterface $store
     * @return StoreInterface
     * @throws CouldNotSaveException
     */
    public function save(StoreInterface $store): StoreInterface;

    /**
     * @param int $storeId
     * @return StoreInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $storeId): StoreInterface;

    /**
     * Load a store by its merchant-defined unique code.
     *
     * @param string $code
     * @return StoreInterface
     * @throws NoSuchEntityException
     */
    public function getByCode(string $code): StoreInterface;

    /**
     * @param StoreInterface $store
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(StoreInterface $store): bool;

    /**
     * @param int $storeId
     * @return bool
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $storeId): bool;

    /**
     * Search for stores by SearchCriteria. Used by REST/SOAP webapi.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * Return all active stores ordered by sort_order ASC.
     *
     * Convenience helper for the carrier + storefront list — avoids the
     * SearchCriteria boilerplate for the most common query.
     *
     * @return StoreInterface[]
     */
    public function getAllActive(): array;
}
