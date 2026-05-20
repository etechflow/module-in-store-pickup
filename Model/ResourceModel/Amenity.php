<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Amenity extends AbstractDb
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('etechflow_isp_amenity', 'amenity_id');
    }
}
