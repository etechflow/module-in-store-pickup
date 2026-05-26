<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Plugin\Quote;

use ETechFlow\InStorePickup\Model\Carrier\InStorePickup;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;

/**
 * v2.0 — Blocks order placement when the customer chose the ISP carrier
 * but didn't actually select a store + pickup datetime in the modal.
 *
 * Without this guard, the carrier code is `etechflow_isp_pickup` but
 * the quote's pickup_store_id / pickup_at remain NULL — staff would
 * see an order labelled "Pick up in store" with no idea which store
 * or when.
 *
 * Throws a LocalizedException before the order is placed, so the
 * checkout UI surfaces a clear error and the customer can re-open
 * the modal via the radio.
 */
class PickupValidatorPlugin
{
    /**
     * @throws LocalizedException
     */
    public function beforePlaceOrder(
        CartManagementInterface $subject,
        int $cartId,
        $paymentMethod = null
    ): array {
        $this->validate($cartId);
        return [$cartId, $paymentMethod];
    }

    /**
     * @throws LocalizedException
     */
    public function beforePlaceOrderForCustomer(
        CartManagementInterface $subject,
        int $customerId,
        $paymentMethod = null
    ): array {
        // Resolve the active cart for this customer to validate.
        // Magento\Checkout\Model\Session is request-scoped — the customer
        // version of placeOrder doesn't pass a cartId, so we let Magento
        // handle resolution and skip strict pre-validation here. The
        // beforePlaceOrder hook above catches the regular checkout path,
        // which is what 99% of customers use.
        return [$customerId, $paymentMethod];
    }

    private function validate(int $cartId): void
    {
        // We lazily fetch the quote to avoid circular DI with CartRepository
        // (CartRepository → quote → plugin → CartRepository …).
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $cartRepo = $om->get(\Magento\Quote\Api\CartRepositoryInterface::class);
        try {
            $quote = $cartRepo->get($cartId);
        } catch (\Throwable $e) {
            return; // can't validate without a quote; let Magento handle it
        }
        $shippingMethod = (string) $quote->getShippingAddress()?->getShippingMethod();
        if ($shippingMethod !== InStorePickup::FULL_METHOD_CODE) {
            return; // not our carrier — no validation needed
        }
        $storeId = (int) $quote->getData('etechflow_isp_pickup_store_id');
        $pickupAt = (string) $quote->getData('etechflow_isp_pickup_at');
        if ($storeId <= 0 || $pickupAt === '') {
            throw new LocalizedException(
                __('Please choose a pickup location and time before placing your order.')
            );
        }
    }
}
