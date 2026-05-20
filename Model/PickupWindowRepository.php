<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model;

use ETechFlow\InStorePickup\Api\Data\PickupWindowInterface;
use ETechFlow\InStorePickup\Api\PickupWindowRepositoryInterface;
use ETechFlow\InStorePickup\Model\ResourceModel\PickupWindow as PickupWindowResource;
use ETechFlow\InStorePickup\Model\ResourceModel\PickupWindow\CollectionFactory as PickupWindowCollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class PickupWindowRepository implements PickupWindowRepositoryInterface
{
    public function __construct(
        private readonly PickupWindowResource $resource,
        private readonly PickupWindowFactory $windowFactory,
        private readonly PickupWindowCollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory
    ) {
    }

    public function save(PickupWindowInterface $window): PickupWindowInterface
    {
        try {
            /** @var PickupWindow $window */
            $this->resource->save($window);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save pickup window: %1', $e->getMessage()), $e);
        }
        return $window;
    }

    public function getById(int $windowId): PickupWindowInterface
    {
        $window = $this->windowFactory->create();
        $this->resource->load($window, $windowId);
        if (!$window->getWindowId()) {
            throw new NoSuchEntityException(__('Pickup window with id "%1" not found.', $windowId));
        }
        return $window;
    }

    public function delete(PickupWindowInterface $window): bool
    {
        try {
            /** @var PickupWindow $window */
            $this->resource->delete($window);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete pickup window: %1', $e->getMessage()), $e);
        }
        return true;
    }

    public function deleteById(int $windowId): bool
    {
        return $this->delete($this->getById($windowId));
    }

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
}
