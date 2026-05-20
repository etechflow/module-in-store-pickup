<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model;

use ETechFlow\InStorePickup\Api\Data\StoreInterface;
use ETechFlow\InStorePickup\Api\StoreRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Quote\Model\Quote;

/**
 * Decide whether a given order or quote is an in-store pickup,
 * and which store it's assigned to.
 *
 * The shipping method on a pickup order looks like
 * `etechflow_isp_<store_code>` — both halves are produced by our
 * carrier. Parsing them out lets observers / blocks / emails
 * answer "is this a pickup?" + "which store?" in one place
 * instead of repeating the prefix check everywhere.
 */
class PickupOrderDetector
{
    /** Method-code prefix our carrier uses. */
    public const METHOD_PREFIX = 'etechflow_isp_';

    public function __construct(
        private readonly StoreRepositoryInterface $storeRepository
    ) {
    }

    /**
     * @param OrderInterface $order
     * @return bool
     */
    public function isPickupOrder(OrderInterface $order): bool
    {
        $method = (string) $order->getShippingMethod();
        return $method !== '' && str_starts_with($method, self::METHOD_PREFIX);
    }

    /**
     * @param Quote $quote
     * @return bool
     */
    public function isPickupQuote(Quote $quote): bool
    {
        $address = $quote->getShippingAddress();
        if (!$address) {
            return false;
        }
        $method = (string) $address->getShippingMethod();
        return $method !== '' && str_starts_with($method, self::METHOD_PREFIX);
    }

    /**
     * Extract the store code from a `etechflow_isp_<store_code>`
     * shipping-method string. Returns '' when the method is not a
     * pickup method.
     *
     * @param string $method
     * @return string
     */
    public function extractStoreCode(string $method): string
    {
        if (!str_starts_with($method, self::METHOD_PREFIX)) {
            return '';
        }
        return substr($method, strlen(self::METHOD_PREFIX));
    }

    /**
     * Resolve the Store entity an order belongs to. Returns null when
     * the order isn't a pickup or the store no longer exists.
     *
     * @param OrderInterface $order
     * @return StoreInterface|null
     */
    public function getStoreForOrder(OrderInterface $order): ?StoreInterface
    {
        $code = $this->extractStoreCode((string) $order->getShippingMethod());
        if ($code === '') {
            return null;
        }
        try {
            return $this->storeRepository->getByCode($code);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }
}
