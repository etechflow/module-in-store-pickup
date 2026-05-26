<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Observer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * v2.0 — Mirrors etechflow_isp_pickup_at from sales_order to
 * sales_order_grid right after order placement, so the admin orders
 * grid can sort/filter by pickup datetime.
 *
 * The sales_order_grid is denormalised — Magento doesn't auto-sync
 * custom columns. Adding to the standard `Magento\Sales\Model\ResourceModel\Order\Grid`
 * via di.xml is the cleaner pattern, but observing `sales_order_place_after`
 * is more conservative and doesn't depend on the grid's internal column-mapping
 * machinery (which Magento's been refactoring across versions).
 */
class OrderGridSyncObserver implements ObserverInterface
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Order|null $order */
        $order = $observer->getEvent()->getOrder();
        if (!$order || !$order->getId()) {
            return;
        }
        $pickupAt = $order->getData('etechflow_isp_pickup_at');
        if (!$pickupAt) {
            return;
        }
        try {
            $conn = $this->resource->getConnection();
            $conn->update(
                $this->resource->getTableName('sales_order_grid'),
                ['etechflow_isp_pickup_at' => $pickupAt],
                ['entity_id = ?' => (int) $order->getId()]
            );
        } catch (\Throwable $e) {
            $this->logger->warning(
                '[ETechFlow_ISP] Failed to sync pickup_at to sales_order_grid: ' . $e->getMessage()
            );
        }
    }
}
