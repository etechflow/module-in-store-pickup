<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\Notification;

use ETechFlow\InStorePickup\Api\Data\StoreInterface;
use ETechFlow\InStorePickup\Model\Store\HoursManager;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends the "your order is ready to collect" email to the customer.
 *
 * Fired by the MarkReady admin controller. The address/hours summary is
 * snapshotted into the email so the customer sees the location details
 * at the time they were notified — not a (potentially newer) version.
 *
 * Failure policy:
 *   - No customer email → log and return false (template requires `addTo`).
 *   - Transport failure → log and return false; the controller decides
 *     whether to surface that to the admin UI.
 */
class PickupReadySender
{
    public const TEMPLATE_ID = 'etechflow_isp_pickup_ready';

    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly HoursManager $hoursManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param OrderInterface $order
     * @param StoreInterface $pickupStore
     * @return bool
     */
    public function send(OrderInterface $order, StoreInterface $pickupStore): bool
    {
        $customerEmail = (string) ($order->getCustomerEmail() ?? '');
        if ($customerEmail === '') {
            $this->logger->warning(
                'ETechFlow_InStorePickup: skipping pickup-ready email — order has no customer email.',
                ['order_increment' => $order->getIncrementId()]
            );
            return false;
        }

        try {
            $magentoStoreId = (int) $order->getStoreId();
            $address = $this->buildStoreAddress($pickupStore);
            $hoursSummary = $this->buildHoursSummary((int) $pickupStore->getStoreId());

            $transport = $this->transportBuilder
                ->setTemplateIdentifier(self::TEMPLATE_ID)
                ->setTemplateOptions([
                    'area'  => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $magentoStoreId ?: $this->storeManager->getStore()->getId(),
                ])
                ->setTemplateVars([
                    'order'                 => $order,
                    'pickup_store'          => $pickupStore,
                    'pickup_store_name'     => (string) $pickupStore->getName(),
                    'pickup_store_address'  => $address,
                    'pickup_store_phone'    => (string) ($pickupStore->getPhone() ?? ''),
                    'pickup_instructions'   => (string) ($pickupStore->getPickupInstructions() ?? ''),
                    'store_hours_summary'   => $hoursSummary,
                    'increment_id'          => (string) $order->getIncrementId(),
                    'customer_name'         => trim((string) $order->getCustomerFirstname() . ' ' . (string) $order->getCustomerLastname()),
                ])
                ->setFromByScope('general', $magentoStoreId)
                ->addTo($customerEmail)
                ->getTransport();

            $transport->sendMessage();
            return true;
        } catch (\Throwable $e) {
            $this->logger->error(
                'ETechFlow_InStorePickup: pickup-ready email failed to send.',
                [
                    'exception'       => $e->getMessage(),
                    'order_increment' => $order->getIncrementId(),
                    'store_code'      => $pickupStore->getCode(),
                ]
            );
            return false;
        }
    }

    private function buildStoreAddress(StoreInterface $store): string
    {
        $parts = array_filter([
            (string) ($store->getStreet() ?? ''),
            trim(((string) ($store->getCity() ?? '')) . ' ' . ((string) ($store->getPostcode() ?? ''))),
            (string) ($store->getCountryCode() ?? ''),
        ], static fn (string $v): bool => $v !== '');
        return implode(', ', $parts);
    }

    /**
     * One-line "Mon-Fri 09:00-17:00, Sat 10:00-14:00, Sun closed" summary.
     */
    private function buildHoursSummary(int $storeId): string
    {
        $rows = $this->hoursManager->getRows($storeId);
        $labels = [0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];
        $parts = [];
        foreach ([1, 2, 3, 4, 5, 6, 0] as $weekday) {
            $r = $rows[$weekday];
            if ($r['is_closed']) {
                $parts[] = $labels[$weekday] . ' closed';
            } else {
                $parts[] = $labels[$weekday] . ' ' . $r['open_time'] . '-' . $r['close_time'];
            }
        }
        return implode(', ', $parts);
    }
}
