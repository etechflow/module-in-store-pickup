<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model;

use ETechFlow\InStorePickup\Api\Data\HolidayInterface;
use ETechFlow\InStorePickup\Api\HolidayRepositoryInterface;
use ETechFlow\InStorePickup\Model\ResourceModel\Holiday as HolidayResource;
use ETechFlow\InStorePickup\Model\ResourceModel\Holiday\CollectionFactory as HolidayCollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class HolidayRepository implements HolidayRepositoryInterface
{
    public function __construct(
        private readonly HolidayResource $resource,
        private readonly HolidayFactory $holidayFactory,
        private readonly HolidayCollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory
    ) {
    }

    public function save(HolidayInterface $holiday): HolidayInterface
    {
        try {
            /** @var Holiday $holiday */
            $this->resource->save($holiday);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save holiday: %1', $e->getMessage()), $e);
        }
        return $holiday;
    }

    public function getById(int $holidayId): HolidayInterface
    {
        $holiday = $this->holidayFactory->create();
        $this->resource->load($holiday, $holidayId);
        if (!$holiday->getHolidayId()) {
            throw new NoSuchEntityException(__('Holiday with id "%1" not found.', $holidayId));
        }
        return $holiday;
    }

    public function delete(HolidayInterface $holiday): bool
    {
        try {
            /** @var Holiday $holiday */
            $this->resource->delete($holiday);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete holiday: %1', $e->getMessage()), $e);
        }
        return true;
    }

    public function deleteById(int $holidayId): bool
    {
        return $this->delete($this->getById($holidayId));
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
