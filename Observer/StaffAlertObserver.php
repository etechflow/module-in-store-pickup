<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Observer;

use ETechFlow\InStorePickup\Model\Config;
use ETechFlow\InStorePickup\Model\Notification\StaffAlertSender;
use ETechFlow\InStorePickup\Model\Performance\Profiler;
use ETechFlow\InStorePickup\Model\PickupOrderDetector;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

/**
 * Observer on `sales_order_place_after`.
 *
 * For pickup orders, looks up the picked store and fires a staff
 * alert email so the staff at that location know they have a new
 * order to pick + pack.
 *
 * Non-pickup orders: short-circuits at the first check, zero cost.
 * Failure: silent + logged. Never blocks order placement.
 */
class StaffAlertObserver implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly PickupOrderDetector $detector,
        private readonly StaffAlertSender $sender,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled() || !$this->config->isSendStaffAlert()) {
            return;
        }

        try {
            /** @var OrderInterface|null $order */
            $order = $observer->getEvent()->getData('order');
            if (!$order instanceof OrderInterface) {
                return;
            }
            if (!$this->detector->isPickupOrder($order)) {
                return;
            }

            $span = Profiler::start('ETechFlow_ISP_StaffAlert');
            try {
                $store = $this->detector->getStoreForOrder($order);
                if ($store === null) {
                    $this->logger->warning(
                        'ETechFlow_InStorePickup: pickup order references unknown store — skipping staff alert.',
                        [
                            'order_increment' => $order->getIncrementId(),
                            'shipping_method' => $order->getShippingMethod(),
                        ]
                    );
                    return;
                }
                $this->sender->send($order, $store);
            } finally {
                Profiler::stop($span);
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                'ETechFlow_InStorePickup: staff alert observer failed.',
                ['exception' => $e->getMessage()]
            );
        }
    }
}
