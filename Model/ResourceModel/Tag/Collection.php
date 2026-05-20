<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\ResourceModel\Tag;

use ETechFlow\InStorePickup\Model\ResourceModel\Tag as TagResource;
use ETechFlow\InStorePickup\Model\Tag as TagModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /** @var string */
    protected $_idFieldName = 'tag_id';

    /** @var string */
    protected $_eventPrefix = 'etechflow_isp_tag_collection';

    /** @var string */
    protected $_eventObject = 'tag_collection';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(TagModel::class, TagResource::class);
    }
}
