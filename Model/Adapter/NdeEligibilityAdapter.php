<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\Adapter;

use ETechFlow\InStorePickup\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Optional adapter that delegates per-item stock-eligibility checks to
 * ETechFlow_NextDayEligibility's `IneligibilityChecker` when NDE is
 * installed AND the merchant has opted in via `use_nde_eligibility = 1`.
 *
 * Standalone-first: when NDE isn't installed, this adapter no-ops and
 * the carrier falls back to its own simpler MSI source-stock check.
 *
 * Why this matters: NDE's checker handles drop-ship + supplier mode
 * (S1/S2 attributes) + force-standard-shipping-only overrides. A
 * merchant who's already configured those rules for next-day shipping
 * gets them automatically for in-store pickup too, with no extra config.
 *
 * Failure modes:
 *   - NDE not installed → `isAvailable()` returns false, no work
 *   - NDE installed but admin opted out → no work
 *   - NDE throws → log + return "all items pickable" so checkout
 *     never breaks because of a sibling-module failure
 */
class NdeEligibilityAdapter
{
    /** @var object|null Cached IneligibilityChecker instance (lazily resolved). */
    private ?object $ndeChecker = null;

    /** @var bool|null Cached availability check. */
    private ?bool $available = null;

    public function __construct(
        private readonly Config $config,
        private readonly \Magento\Framework\ObjectManagerInterface $objectManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Whether the NDE adapter is usable in the current install — module
     * class is loadable + merchant has opted in via admin config.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        if (!class_exists('\ETechFlow\NextDayEligibility\Model\IneligibilityChecker')) {
            return $this->available = false;
        }

        return $this->available = $this->config->isUseNdeEligibility();
    }

    /**
     * Check whether a given cart has at least one item that NDE considers
     * ineligible. Used by the ISP carrier (later in v1.0+) to decide
     * whether to offer pickup at all.
     *
     * @param array<int, \Magento\Quote\Model\Quote\Item> $items
     * @return bool true = at least one item is ineligible (pickup should be hidden)
     */
    public function hasIneligibleItems(array $items): bool
    {
        if (!$this->isAvailable() || empty($items)) {
            return false;
        }

        try {
            if ($this->ndeChecker === null) {
                $this->ndeChecker = $this->objectManager->get(
                    '\ETechFlow\NextDayEligibility\Model\IneligibilityChecker'
                );
            }

            return (bool) $this->ndeChecker->hasIneligibleItems($items);
        } catch (\Throwable $e) {
            $this->logger->warning(
                'ETechFlow_InStorePickup: NDE adapter failed; treating cart as eligible.',
                ['exception' => $e->getMessage()]
            );
            return false;
        }
    }
}
