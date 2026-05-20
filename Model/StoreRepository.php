<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model;

use ETechFlow\InStorePickup\Api\Data\StoreInterface;
use ETechFlow\InStorePickup\Api\StoreRepositoryInterface;
use ETechFlow\InStorePickup\Model\ResourceModel\Store as StoreResource;
use ETechFlow\InStorePickup\Model\ResourceModel\Store\CollectionFactory as StoreCollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * StoreRepository — standard Magento service-contract repository.
 *
 * Per-request memoization of getById/getByCode keeps repeated reads
 * cheap. Cache flushed on save/delete so admin edits propagate within
 * the same request.
 */
class StoreRepository implements StoreRepositoryInterface
{
    /** @var array<int, StoreInterface> */
    private array $byIdCache = [];

    /** @var array<string, StoreInterface> */
    private array $byCodeCache = [];

    public function __construct(
        private readonly StoreResource $resource,
        private readonly StoreFactory $storeFactory,
        private readonly StoreCollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(StoreInterface $store): StoreInterface
    {
        try {
            /** @var Store $store */
            $this->resource->save($store);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save store: %1', $e->getMessage()), $e);
        }
        $this->invalidateCache();
        return $store;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $storeId): StoreInterface
    {
        if (isset($this->byIdCache[$storeId])) {
            return $this->byIdCache[$storeId];
        }

        $store = $this->storeFactory->create();
        $this->resource->load($store, $storeId);
        if (!$store->getStoreId()) {
            throw new NoSuchEntityException(__('Store with id "%1" not found.', $storeId));
        }
        $this->byIdCache[$storeId] = $store;
        $this->byCodeCache[$store->getCode()] = $store;
        return $store;
    }

    /**
     * @inheritDoc
     */
    public function getByCode(string $code): StoreInterface
    {
        if (isset($this->byCodeCache[$code])) {
            return $this->byCodeCache[$code];
        }

        $store = $this->storeFactory->create();
        $this->resource->load($store, $code, 'code');
        if (!$store->getStoreId()) {
            throw new NoSuchEntityException(__('Store with code "%1" not found.', $code));
        }
        $this->byCodeCache[$code] = $store;
        $this->byIdCache[$store->getStoreId()] = $store;
        return $store;
    }

    /**
     * @inheritDoc
     */
    public function delete(StoreInterface $store): bool
    {
        try {
            /** @var Store $store */
            $this->resource->delete($store);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete store: %1', $e->getMessage()), $e);
        }
        $this->invalidateCache();
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $storeId): bool
    {
        return $this->delete($this->getById($storeId));
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $results = $this->searchResultsFactory->create();
        $results->setSearchCriteria($searchCriteria);
        $results->setItems($collection->getItems());
        $results->setTotalCount($collection->getSize());
        return $results;
    }

    /**
     * @inheritDoc
     */
    public function getAllActive(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addActiveFilter();
        $collection->addSortOrderAsc();
        return array_values($collection->getItems());
    }

    /**
     * Clear per-request memoization on every write.
     *
     * @return void
     */
    private function invalidateCache(): void
    {
        $this->byIdCache = [];
        $this->byCodeCache = [];
    }
}
