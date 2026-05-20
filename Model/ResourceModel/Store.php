<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Resource model for `etechflow_isp_store`.
 *
 * Magento standard pattern — extends AbstractDb, declares mainTable +
 * primary-key column. All CRUD plumbing handled by the framework.
 */
class Store extends AbstractDb
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('etechflow_isp_store', 'store_id');
    }
}
