<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for weekday + exception "is_closed" dropdowns.
 *
 * Renders the dropdown as "Open" / "Closed" instead of the generic
 * Yes / No that Magento\Config\Model\Config\Source\Yesno produces.
 *
 * Underlying data is unchanged — same int 0/1 as is_closed has always
 * been (0 = open, 1 = closed). Only the user-facing label changes,
 * so:
 *   - DB column stays smallint is_closed (no migration)
 *   - Form binding: "Open" → 0 → is_closed = 0 → use times
 *                   "Closed" → 1 → is_closed = 1 → ignore times
 *
 * Customer feedback (Keystation) made the case: a "Yes / No" dropdown
 * above unlabelled weekday rows is genuinely confusing — "Yes" reads
 * as a confirmation rather than "yes, closed". The "Open / Closed"
 * labels are self-documenting.
 */
class OpenClosed implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 0, 'label' => __('Open')],
            ['value' => 1, 'label' => __('Closed')],
        ];
    }
}
