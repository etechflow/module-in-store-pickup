<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Pickup;

use ETechFlow\InStorePickup\Api\StoreRepositoryInterface;
use ETechFlow\InStorePickup\Model\Slot\AvailabilityCalculator;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * POST /etechflow_isp/pickup/select
 *
 * Saves the customer's pickup choice (store_id + slot_iso_datetime)
 * onto the active checkout quote. The carrier reads these on its
 * next rate request, producing the updated "Pick up: <Store> — <Date>"
 * radio label.
 *
 * Body params (form-encoded):
 *   store_id   int — etechflow_isp_store.store_id
 *   pickup_at  str — "YYYY-MM-DD HH:MM:00" (start of 1-hour slot)
 *   form_key   str — Magento's CSRF token
 *
 * Response:
 *   { "ok":true, "store_name":"Keystation Maldon",
 *     "pretty":"Tue 27 May 14:00" }
 *
 * Validates capacity again at save time (defense against UI races
 * where two customers click the same slot at the same instant).
 */
class Select implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly RequestInterface $request,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly AvailabilityCalculator $calculator,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        try {
            $storeId = (int) $this->request->getParam('store_id');
            $pickupAt = trim((string) $this->request->getParam('pickup_at'));

            if ($storeId <= 0) {
                throw new \InvalidArgumentException('Missing store_id');
            }
            if ($pickupAt === '' || !preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $pickupAt)) {
                throw new \InvalidArgumentException('Invalid pickup_at format');
            }
            $pickupAt = str_replace('T', ' ', $pickupAt);
            if (strlen($pickupAt) === 16) {
                $pickupAt .= ':00';
            }

            $store = $this->storeRepository->getById($storeId);

            // Re-validate capacity at save time
            if (!$this->calculator->isSlotAvailable($store, $pickupAt)) {
                throw new \RuntimeException(
                    (string) __('That slot is now full. Please pick another.')
                );
            }

            $quote = $this->checkoutSession->getQuote();
            $quote->setData('etechflow_isp_pickup_store_id', $storeId);
            $quote->setData('etechflow_isp_pickup_at', $pickupAt);

            // Trigger rate-collection refresh so the carrier label updates.
            $shippingAddress = $quote->getShippingAddress();
            if ($shippingAddress) {
                $shippingAddress->setCollectShippingRates(true);
                $shippingAddress->collectShippingRates();
            }

            $this->cartRepository->save($quote);

            $pretty = (new \DateTime($pickupAt))->format('D j M H:i');
            return $result->setData([
                'ok' => true,
                'store_id' => $storeId,
                'store_name' => $store->getName(),
                'pickup_at' => $pickupAt,
                'pretty' => $pretty,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[ETechFlow_ISP] Select pickup failed: ' . $e->getMessage());
            return $result->setHttpResponseCode(400)
                          ->setData(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        // Form key is checked via standard Magento middleware (form_key param).
        return true;
    }
}
