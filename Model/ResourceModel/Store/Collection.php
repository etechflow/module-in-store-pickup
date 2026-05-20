<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\ResourceModel\Store;

use ETechFlow\InStorePickup\Model\ResourceModel\Store as StoreResource;
use ETechFlow\InStorePickup\Model\Store as StoreModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Collection for stores. Standard Magento AbstractCollection — gives us
 * filter / sort / paginate / load() over `etechflow_isp_store`.
 */
class Collection extends AbstractCollection
{
    /** @var string */
    protected $_idFieldName = 'store_id';

    /** @var string */
    protected $_eventPrefix = 'etechflow_isp_store_collection';

    /** @var string */
    protected $_eventObject = 'store_collection';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(StoreModel::class, StoreResource::class);
    }

    /**
     * Convenience filter: only stores flagged as active.
     *
     * @return self
     */
    public function addActiveFilter(): self
    {
        $this->addFieldToFilter('is_active', 1);
        return $this;
    }

    /**
     * Convenience sort: by sort_order ASC, then store_id ASC for deterministic ties.
     *
     * @return self
     */
    public function addSortOrderAsc(): self
    {
        $this->setOrder('sort_order', 'ASC');
        $this->setOrder('store_id', 'ASC');
        return $this;
    }
}
