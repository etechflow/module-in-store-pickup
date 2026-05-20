<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\Notification;

use ETechFlow\InStorePickup\Api\Data\StoreInterface;
use ETechFlow\InStorePickup\Model\Config;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends the "new pickup order" alert email to store staff.
 *
 * Fired by the StaffAlertObserver on `sales_order_place_after`.
 * Email goes to the picked store's configured `email` field (the
 * staff inbox for that location). Falls back to the store admin's
 * general sender email if the store has no contact email.
 *
 * Failure modes (silent + logged):
 *   - Store has no email → log a warning, skip send
 *   - Transport fails → log error, do NOT re-throw (would block order placement)
 */
class StaffAlertSender
{
    public const TEMPLATE_ID = 'etechflow_isp_staff_alert';

    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param OrderInterface $order
     * @param StoreInterface $pickupStore
     * @return bool true on success, false on silent failure
     */
    public function send(OrderInterface $order, StoreInterface $pickupStore): bool
    {
        if (!$this->config->isSendStaffAlert()) {
            return false;
        }

        $staffEmail = (string) ($pickupStore->getEmail() ?? '');
        if ($staffEmail === '') {
            $this->logger->warning(
                'ETechFlow_InStorePickup: skipping staff alert — store has no contact email.',
                ['store_code' => $pickupStore->getCode(), 'order_increment' => $order->getIncrementId()]
            );
            return false;
        }

        try {
            $magentoStoreId = (int) $order->getStoreId();
            $transport = $this->transportBuilder
                ->setTemplateIdentifier(self::TEMPLATE_ID)
                ->setTemplateOptions([
                    'area'  => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $magentoStoreId ?: $this->storeManager->getStore()->getId(),
                ])
                ->setTemplateVars([
                    'order'             => $order,
                    'pickup_store'      => $pickupStore,
                    'pickup_store_name' => (string) $pickupStore->getName(),
                    'pickup_store_phone'=> (string) ($pickupStore->getPhone() ?? ''),
                    'increment_id'      => (string) $order->getIncrementId(),
                    'customer_name'     => trim((string) $order->getCustomerFirstname() . ' ' . (string) $order->getCustomerLastname()),
                    'customer_email'    => (string) $order->getCustomerEmail(),
                    'grand_total'       => (float) $order->getGrandTotal(),
                ])
                ->setFromByScope('general', $magentoStoreId)
                ->addTo($staffEmail)
                ->getTransport();

            $transport->sendMessage();
            return true;
        } catch (\Throwable $e) {
            $this->logger->error(
                'ETechFlow_InStorePickup: staff alert email failed to send.',
                [
                    'exception'      => $e->getMessage(),
                    'order_increment'=> $order->getIncrementId(),
                    'store_code'     => $pickupStore->getCode(),
                ]
            );
            return false;
        }
    }
}
