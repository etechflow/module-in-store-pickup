<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Block\Adminhtml\Sales\Order;

use ETechFlow\InStorePickup\Api\StoreRepositoryInterface;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;

/**
 * v2.0 — Admin order view block: renders the pickup location + slot
 * summary in a dedicated card. Replaces (or augments) the standard
 * "Shipping & Handling Information" line, which only shows the bare
 * method title.
 *
 * Mounted via layout XML into the order view's "order_info" container.
 */
class PickupInfo extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly StoreRepositoryInterface $storeRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getOrder(): ?Order
    {
        return $this->registry->registry('current_order') ?: $this->registry->registry('order');
    }

    /**
     * Returns null if this order isn't an ISP pickup order. Otherwise
     * returns an array with the pickup details for the template.
     *
     * @return array{
     *   store_name:string, store_address:string, store_phone:string,
     *   pickup_at:string, pretty:string
     * }|null
     */
    public function getPickupInfo(): ?array
    {
        $order = $this->getOrder();
        if (!$order) {
            return null;
        }
        $storeId = (int) $order->getData('etechflow_isp_pickup_store_id');
        $pickupAt = (string) $order->getData('etechflow_isp_pickup_at');
        if ($storeId <= 0 || $pickupAt === '') {
            return null;
        }
        try {
            $store = $this->storeRepository->getById($storeId);
        } catch (\Throwable $e) {
            return null;
        }
        $pretty = $this->formatPretty($pickupAt);

        $address = trim(implode(', ', array_filter([
            (string) $store->getStreet(),
            (string) $store->getCity(),
            (string) $store->getPostcode(),
            (string) $store->getCountryCode(),
        ])));

        return [
            'store_name' => (string) $store->getName(),
            'store_address' => $address,
            'store_phone' => (string) $store->getPhone(),
            'pickup_at' => $pickupAt,
            'pretty' => $pretty,
        ];
    }

    private function formatPretty(string $dt): string
    {
        try {
            return (new \DateTime($dt))->format('l j F Y, H:i');
        } catch (\Throwable $e) {
            return $dt;
        }
    }
}
