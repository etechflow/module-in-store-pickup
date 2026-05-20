<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\ResourceModel\PickupWindow;

use ETechFlow\InStorePickup\Model\PickupWindow as PickupWindowModel;
use ETechFlow\InStorePickup\Model\ResourceModel\PickupWindow as PickupWindowResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /** @var string */
    protected $_idFieldName = 'window_id';

    /** @var string */
    protected $_eventPrefix = 'etechflow_isp_pickup_window_collection';

    /** @var string */
    protected $_eventObject = 'pickup_window_collection';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(PickupWindowModel::class, PickupWindowResource::class);
    }
}
