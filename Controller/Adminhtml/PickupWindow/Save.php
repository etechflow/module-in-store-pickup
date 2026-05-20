<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\PickupWindow;

use ETechFlow\InStorePickup\Api\PickupWindowRepositoryInterface;
use ETechFlow\InStorePickup\Model\PickupWindowFactory;
use ETechFlow\InStorePickup\Model\TimeNormalizer;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Psr\Log\LoggerInterface;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::pickup_windows';

    public function __construct(
        Context $context,
        private readonly PickupWindowRepositoryInterface $pickupWindowRepository,
        private readonly PickupWindowFactory $pickupWindowFactory,
        private readonly TimeNormalizer $timeNormalizer,
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
        $data = $this->getRequest()->getPostValue();

        if (empty($data)) {
            return $redirect->setPath('*/*/index');
        }

        $data = $this->normalisePayload($data);

        try {
            $windowId = (int) ($data['window_id'] ?? 0);
            $window = $windowId > 0
                ? $this->pickupWindowRepository->getById($windowId)
                : $this->pickupWindowFactory->create();

            foreach (['code', 'label', 'start_time', 'end_time'] as $required) {
                if (empty($data[$required])) {
                    $this->messageManager->addErrorMessage(__('Field "%1" is required.', $required));
                    return $redirect->setPath('*/*/edit', ['window_id' => $windowId]);
                }
            }

            $normalisedStart = $this->timeNormalizer->normalize($data['start_time']);
            $normalisedEnd   = $this->timeNormalizer->normalize($data['end_time']);
            if ($normalisedStart === null || $normalisedEnd === null) {
                $this->messageManager->addErrorMessage(__('Start/End times could not be understood. Try HH:MM (e.g. 09:00), or "9am", "9", "9:30 PM".'));
                return $redirect->setPath('*/*/edit', ['window_id' => $windowId]);
            }
            $data['start_time'] = $normalisedStart;
            $data['end_time']   = $normalisedEnd;
            if ($data['start_time'] >= $data['end_time']) {
                $this->messageManager->addErrorMessage(__('Start time must be earlier than end time.'));
                return $redirect->setPath('*/*/edit', ['window_id' => $windowId]);
            }

            $window->setData(array_merge($window->getData(), $data));
            $this->pickupWindowRepository->save($window);

            $this->messageManager->addSuccessMessage(__('Pickup window saved: %1', $window->getLabel()));

            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['window_id' => $window->getWindowId()]);
            }
            return $redirect->setPath('*/*/index');
        } catch (\Throwable $e) {
            $this->logger->error('ETechFlow_InStorePickup: pickup-window save failed.', ['exception' => $e->getMessage()]);
            $this->messageManager->addErrorMessage(__('Could not save pickup window: %1', $e->getMessage()));
            return $redirect->setPath('*/*/edit', ['window_id' => (int) ($data['window_id'] ?? 0)]);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalisePayload(array $data): array
    {
        foreach (['code', 'label', 'start_time', 'end_time'] as $f) {
            if (isset($data[$f]) && is_string($data[$f])) {
                $data[$f] = trim($data[$f]);
            }
        }
        $data['is_active']  = !empty($data['is_active']) ? 1 : 0;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['capacity']   = (int) ($data['capacity'] ?? 0);
        return $data;
    }

    private function isHhMm(string $value): bool
    {
        return (bool) preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value);
    }
}
