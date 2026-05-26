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
 * GET /etechflow_isp/pickup/dates?store_id=N
 *
 * Returns a list of bookable dates for the given store. v2.0 policy
 * (per build plan): next day onwards, +14 days. Closed weekdays and
 * holiday days are filtered out.
 *
 * Response shape:
 *   { "dates": [
 *       { "iso":"2026-05-23", "label":"Sat 23 May", "weekday":6 },
 *       { "iso":"2026-05-26", "label":"Tue 26 May", "weekday":2 },
 *       ...
 *     ] }
 *
 * Storefront step 2 (date picker) consumes this. The customer sees
 * only days the chosen store will actually accept their pickup.
 */
class Dates implements HttpGetActionInterface, CsrfAwareActionInterface
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
        if ($storeId <= 0) {
            return $result->setData(['dates' => []]);
        }
        try {
            $store = $this->storeRepository->getById($storeId);
            $dates = $this->calculator->getAvailableDates($store);
            return $result->setData(['dates' => $dates]);
        } catch (\Throwable $e) {
            return $result->setData(['dates' => []]);
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
