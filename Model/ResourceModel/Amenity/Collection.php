<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\ResourceModel\Amenity;

use ETechFlow\InStorePickup\Model\Amenity as AmenityModel;
use ETechFlow\InStorePickup\Model\ResourceModel\Amenity as AmenityResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /** @var string */
    protected $_idFieldName = 'amenity_id';

    /** @var string */
    protected $_eventPrefix = 'etechflow_isp_amenity_collection';

    /** @var string */
    protected $_eventObject = 'amenity_collection';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(AmenityModel::class, AmenityResource::class);
    }
}
