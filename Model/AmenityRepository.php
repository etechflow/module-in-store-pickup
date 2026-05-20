<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model;

use ETechFlow\InStorePickup\Api\AmenityRepositoryInterface;
use ETechFlow\InStorePickup\Api\Data\AmenityInterface;
use ETechFlow\InStorePickup\Model\ResourceModel\Amenity as AmenityResource;
use ETechFlow\InStorePickup\Model\ResourceModel\Amenity\CollectionFactory as AmenityCollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class AmenityRepository implements AmenityRepositoryInterface
{
    public function __construct(
        private readonly AmenityResource $resource,
        private readonly AmenityFactory $amenityFactory,
        private readonly AmenityCollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory
    ) {
    }

    public function save(AmenityInterface $amenity): AmenityInterface
    {
        try {
            /** @var Amenity $amenity */
            $this->resource->save($amenity);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save amenity: %1', $e->getMessage()), $e);
        }
        return $amenity;
    }

    public function getById(int $amenityId): AmenityInterface
    {
        $amenity = $this->amenityFactory->create();
        $this->resource->load($amenity, $amenityId);
        if (!$amenity->getAmenityId()) {
            throw new NoSuchEntityException(__('Amenity with id "%1" not found.', $amenityId));
        }
        return $amenity;
    }

    public function delete(AmenityInterface $amenity): bool
    {
        try {
            /** @var Amenity $amenity */
            $this->resource->delete($amenity);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete amenity: %1', $e->getMessage()), $e);
        }
        return true;
    }

    public function deleteById(int $amenityId): bool
    {
        return $this->delete($this->getById($amenityId));
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
