<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model;

/**
 * Generates the customer-facing pickup verification code.
 *
 * The code prints on the order confirmation + the pickup-ready email
 * and is what the customer shows in-store at collection. v1.0 returns
 * a numeric code (4-8 digits, configurable). v1.1+ could add an
 * alphanumeric mode.
 *
 * Cryptographically random (via `random_int`) — not predictable from
 * order id / customer email / timestamp.
 */
class PickupCodeGenerator
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * Generate a fresh pickup code respecting the merchant's configured
     * length. Returns a zero-padded numeric string.
     *
     * @return string
     */
    public function generate(): string
    {
        $length = $this->config->getPickupCodeLength();
        $max = (10 ** $length) - 1;
        $value = random_int(0, $max);
        return str_pad((string) $value, $length, '0', STR_PAD_LEFT);
    }
}
