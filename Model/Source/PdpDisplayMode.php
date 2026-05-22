<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\Source;

use ETechFlow\InStorePickup\Block\Catalog\Product\PickupAvailability;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for the PDP widget's "Display Mode" select.
 *
 * Two-option dropdown; values mirror the constants on PickupAvailability so
 * config + block read identical strings.
 */
class PdpDisplayMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => PickupAvailability::MODE_SIMPLE,    'label' => __('Simple notice')],
            ['value' => PickupAvailability::MODE_PER_STORE, 'label' => __('Per-store list with stock')],
        ];
    }
}
