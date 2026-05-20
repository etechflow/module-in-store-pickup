<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Ui\Component\Listing;

use ETechFlow\InStorePickup\Model\ResourceModel\Store\CollectionFactory as StoreCollectionFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider as UiDataProvider;

/**
 * UI Component DataProvider for the Stores grid.
 *
 * Standard pattern — wraps the Store collection in a UI-Component-compatible
 * search-result shape. Filters, pagination, sorting are handled by the
 * framework against the underlying collection.
 */
class DataProvider extends UiDataProvider
{
    /**
     * @param string                 $name
     * @param string                 $primaryFieldName
     * @param string                 $requestFieldName
     * @param ReportingInterface     $reporting
     * @param SearchCriteriaBuilder  $searchCriteriaBuilder
     * @param RequestInterface       $request
     * @param FilterBuilder          $filterBuilder
     * @param StoreCollectionFactory $collectionFactory
     * @param array                  $meta
     * @param array                  $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        StoreCollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
        $this->collection = $collectionFactory->create();
    }
}
