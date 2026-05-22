<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Pickup;

use ETechFlow\InStorePickup\Api\StoreRepositoryInterface;
use ETechFlow\InStorePickup\Model\Slot\AvailabilityCalculator;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * GET /etechflow_isp/pickup/slots?store_id=N&date=YYYY-MM-DD
 *
 * Returns 1-hour bookable slots for the store on the given date.
 * Each slot is checked against the store's slot_capacity vs. the
 * count of existing bookings (quote + order) at that exact start
 * time. Full slots are returned with `available=false` so the UI
 * can grey them out instead of hiding them.
 *
 * Response shape:
 *   { "slots": [
 *       { "start":"09:00", "end":"10:00", "iso":"2026-05-27T09:00:00",
 *         "available":true,  "remaining":7 },
 *       { "start":"10:00", "end":"11:00", "iso":"2026-05-27T10:00:00",
 *         "available":false, "remaining":0 },
 *       ...
 *     ] }
 */
class Slots implements HttpGetActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly RequestInterface $request,
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly AvailabilityCalculator $calculator
    ) {
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $storeId = (int) $this->request->getParam('store_id');
        $date = (string) $this->request->getParam('date');
        if ($storeId <= 0 || $date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $result->setData(['slots' => []]);
        }
        try {
            $store = $this->storeRepository->getById($storeId);
            $slots = $this->calculator->getSlotsForDate($store, $date);
            return $result->setData(['slots' => $slots]);
        } catch (\Throwable $e) {
            return $result->setData(['slots' => []]);
        }
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
