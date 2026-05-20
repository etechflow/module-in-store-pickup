<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model;

use ETechFlow\InStorePickup\Api\Data\TagInterface;
use ETechFlow\InStorePickup\Api\TagRepositoryInterface;
use ETechFlow\InStorePickup\Model\ResourceModel\Tag as TagResource;
use ETechFlow\InStorePickup\Model\ResourceModel\Tag\CollectionFactory as TagCollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class TagRepository implements TagRepositoryInterface
{
    public function __construct(
        private readonly TagResource $resource,
        private readonly TagFactory $tagFactory,
        private readonly TagCollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(TagInterface $tag): TagInterface
    {
        try {
            /** @var Tag $tag */
            $this->resource->save($tag);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save tag: %1', $e->getMessage()), $e);
        }
        return $tag;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $tagId): TagInterface
    {
        $tag = $this->tagFactory->create();
        $this->resource->load($tag, $tagId);
        if (!$tag->getTagId()) {
            throw new NoSuchEntityException(__('Tag with id "%1" not found.', $tagId));
        }
        return $tag;
    }

    /**
     * @inheritDoc
     */
    public function delete(TagInterface $tag): bool
    {
        try {
            /** @var Tag $tag */
            $this->resource->delete($tag);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete tag: %1', $e->getMessage()), $e);
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $tagId): bool
    {
        return $this->delete($this->getById($tagId));
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
}
