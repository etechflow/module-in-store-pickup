<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Api;

use ETechFlow\InStorePickup\Api\Data\TagInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Service-contract repository for store tags.
 */
interface TagRepositoryInterface
{
    /**
     * @param TagInterface $tag
     * @return TagInterface
     * @throws CouldNotSaveException
     */
    public function save(TagInterface $tag): TagInterface;

    /**
     * @param int $tagId
     * @return TagInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $tagId): TagInterface;

    /**
     * @param TagInterface $tag
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(TagInterface $tag): bool;

    /**
     * @param int $tagId
     * @return bool
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $tagId): bool;

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;
}
