<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Plugin\Adminhtml;

use ETechFlow\InStorePickup\Controller\Adminhtml\Order\MarkReady;
use ETechFlow\InStorePickup\Model\PickupOrderDetector;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Block\Adminhtml\Order\View;

/**
 * Adds a "Mark Pickup Ready" button to the sales-order admin view page
 * — but only when the order is actually an ETechFlow pickup order and
 * the current admin user has the ACL resource granted.
 *
 * Hook point: beforeSetLayout. Buttons added there land before any
 * standard buttons get rendered, so order doesn't matter — Magento's
 * toolbar sorts by the sortOrder we provide.
 *
 * Plugin scope: adminhtml only (registered in etc/adminhtml/di.xml).
 */
class MarkReadyButtonPlugin
{
    public function __construct(
        private readonly PickupOrderDetector $pickupOrderDetector,
        private readonly AuthorizationInterface $authorization,
        private readonly AuthSession $authSession,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * @param View $subject
     * @return void
     */
    public function beforeSetLayout(View $subject): void
    {
        // Guard 1: ACL
        if (!$this->authorization->isAllowed(MarkReady::ADMIN_RESOURCE)) {
            return;
        }
        // Guard 2: pickup order?
        $order = $subject->getOrder();
        if (!$order || !$this->pickupOrderDetector->isPickupOrder($order)) {
            return;
        }
        // Guard 3: hide if already marked (idempotent — keep the toolbar uncluttered)
        if ($this->alreadyMarked($order)) {
            return;
        }

        $url = $this->urlBuilder->getUrl(
            'etechflow_isp/order/markReady',
            ['order_id' => (int) $order->getId()]
        );
        $customerEmail = (string) ($order->getCustomerEmail() ?? '');
        $confirm = $customerEmail !== ''
            ? (string) __('Send the "pickup ready" email to %1 now? They will be notified the order is ready to collect.', $customerEmail)
            : (string) __('Send the "pickup ready" email to the customer now?');

        $subject->addButton(
            'etechflow_isp_mark_pickup_ready',
            [
                'label'    => __('Mark Pickup Ready'),
                'class'    => 'primary',
                'on_click' => sprintf(
                    'confirmSetLocation(\'%s\', \'%s\')',
                    addslashes($confirm),
                    addslashes($url)
                ),
                'sort_order' => 20,
            ]
        );
    }

    private function alreadyMarked(\Magento\Sales\Api\Data\OrderInterface $order): bool
    {
        foreach ($order->getStatusHistories() ?? [] as $history) {
            $comment = (string) $history->getComment();
            if ($comment !== '' && str_contains($comment, MarkReady::SENT_MARKER)) {
                return true;
            }
        }
        return false;
    }
}
