<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Pickup;

use ETechFlow\InStorePickup\Api\StoreRepositoryInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * GET /etechflow_isp/pickup/stores
 *
 * Returns a list of active pickup stores for the modal's step 1
 * (location selection). Public — no auth required at checkout time.
 *
 * Response shape:
 *   { "stores": [
 *       { "id":1, "code":"keystation_maldon", "name":"Keystation Maldon",
 *         "street":"4 Hall Road, Heybridge", "city":"Maldon Essex",
 *         "postcode":"CM9 4NJ", "phone":"0333 032 9655" },
 *       ...
 *     ] }
 *
 * GET (read-only) — no CSRF token required by Magento, but we
 * still implement CsrfAwareActionInterface to be explicit.
 */
class Stores implements HttpGetActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly StoreRepositoryInterface $storeRepository
    ) {
    }

    public function execute()
    {
        $stores = [];
        try {
            foreach ($this->storeRepository->getAllActive() as $store) {
                $stores[] = [
                    'id' => (int) $store->getId(),
                    'code' => (string) $store->getCode(),
                    'name' => (string) $store->getName(),
                    'street' => (string) $store->getStreet(),
                    'city' => (string) $store->getCity(),
                    'postcode' => (string) $store->getPostcode(),
                    'phone' => (string) $store->getPhone(),
                ];
            }
        } catch (\Throwable $e) {
            // Empty list is the safe degrade.
        }
        return $this->jsonFactory->create()->setData(['stores' => $stores]);
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
