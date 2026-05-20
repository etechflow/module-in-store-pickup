<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Ui\Component\Listing;

use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider as UiDataProvider;

/**
 * Generic UI Component DataProvider — used by Tag/Amenity/PickupWindow/Holiday
 * grids (every admin grid in ISP except Stores, which uses its own typed provider).
 *
 * Each grid wires its own etc/di.xml virtualType into the framework's
 * standard SearchResult — this DataProvider is the typed Magento UI
 * Component grid backend.
 */
class GenericDataProvider extends UiDataProvider
{
    // Inherits everything from Magento's UI Component DataProvider. The
    // per-grid configuration (which collection to use) is set via etc/di.xml
    // CollectionFactory map in the parent module's di.xml.
}
