<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Order;

use ETechFlow\InStorePickup\Model\Notification\PickupReadySender;
use ETechFlow\InStorePickup\Model\PickupOrderDetector;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Admin action: mark a pickup order as ready for collection.
 *
 * Idempotency: a marker comment "[etechflow_isp:pickup_ready_sent]" is
 * appended to the order's status history. If we find it already, refuse
 * to re-send to avoid double-spamming the customer.
 */
class MarkReady extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::mark_pickup_ready';

    /**
     * Marker token appended to the status-history comment so re-clicks
     * can be detected and rejected without adding another column to
     * sales_order.
     */
    public const SENT_MARKER = '[etechflow_isp:pickup_ready_sent]';

    public function __construct(
        Context $context,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PickupOrderDetector $pickupOrderDetector,
        private readonly PickupReadySender $pickupReadySender,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute()
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();
        $orderId  = (int) $this->getRequest()->getParam('order_id');

        if ($orderId <= 0) {
            $this->messageManager->addErrorMessage(__('Missing order_id.'));
            return $redirect->setPath('sales/order/index');
        }

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('Order not found.'));
            return $redirect->setPath('sales/order/index');
        }

        if (!$this->pickupOrderDetector->isPickupOrder($order)) {
            $this->messageManager->addErrorMessage(__('This order is not an in-store pickup order.'));
            return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
        }

        if ($this->wasAlreadyMarked($order)) {
            $this->messageManager->addNoticeMessage(__('This order was already marked ready — customer was already notified.'));
            return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
        }

        $pickupStore = $this->pickupOrderDetector->getStoreForOrder($order);
        if ($pickupStore === null) {
            $this->messageManager->addErrorMessage(__('The pickup store for this order could not be resolved.'));
            return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
        }

        $sent = $this->pickupReadySender->send($order, $pickupStore);

        try {
            $comment = $sent
                ? sprintf(
                    '%s Customer notified that order is ready at %s.',
                    self::SENT_MARKER,
                    (string) $pickupStore->getName()
                )
                : sprintf(
                    '%s Pickup ready marked but email failed to send — check logs.',
                    self::SENT_MARKER
                );
            $order->addCommentToStatusHistory($comment, false, true);
            $this->orderRepository->save($order);
        } catch (\Throwable $e) {
            $this->logger->error(
                'ETechFlow_InStorePickup: failed to record mark-ready status-history comment.',
                ['order_increment' => $order->getIncrementId(), 'exception' => $e->getMessage()]
            );
        }

        if ($sent) {
            $this->messageManager->addSuccessMessage(__(
                'Pickup ready — customer notified by email.'
            ));
        } else {
            $this->messageManager->addWarningMessage(__(
                'Marked as pickup ready, but the email failed to send. See system logs.'
            ));
        }

        return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return bool
     */
    private function wasAlreadyMarked($order): bool
    {
        foreach ($order->getStatusHistories() ?? [] as $history) {
            $comment = (string) $history->getComment();
            if ($comment !== '' && str_contains($comment, self::SENT_MARKER)) {
                return true;
            }
        }
        return false;
    }
}
