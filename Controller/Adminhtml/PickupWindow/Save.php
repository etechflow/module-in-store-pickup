<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\PickupWindow;

use ETechFlow\InStorePickup\Api\PickupWindowRepositoryInterface;
use ETechFlow\InStorePickup\Controller\Adminhtml\AjaxSaveResultTrait;
use ETechFlow\InStorePickup\Model\PickupWindowFactory;
use ETechFlow\InStorePickup\Model\TimeNormalizer;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;

class Save extends Action implements HttpPostActionInterface
{
    use AjaxSaveResultTrait;

    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::pickup_windows';

    private const DATA_PERSISTOR_KEY = 'etechflow_isp_pickupwindow';

    public function __construct(
        Context $context,
        private readonly PickupWindowRepositoryInterface $pickupWindowRepository,
        private readonly PickupWindowFactory $pickupWindowFactory,
        private readonly TimeNormalizer $timeNormalizer,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly JsonFactory $ajaxSaveResultJsonFactory,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $data = $this->getRequest()->getPostValue();
        if (empty($data)) {
            return $this->respondRedirect('*/*/index');
        }

        $data = $this->normalisePayload($data);
        $windowId = (int) ($data['window_id'] ?? 0);

        // v1.1.6 fix: strip window_id=0 so new pickup windows INSERT (not UPDATE WHERE id=0).
        // See Store/Save.php for the full root-cause explanation.
        unset($data['window_id']);

        try {
            $this->dataPersistor->set(self::DATA_PERSISTOR_KEY, $data);

            $window = $windowId > 0
                ? $this->pickupWindowRepository->getById($windowId)
                : $this->pickupWindowFactory->create();

            foreach (['code', 'label', 'start_time', 'end_time'] as $required) {
                if (empty($data[$required])) {
                    $this->messageManager->addErrorMessage(__('Field "%1" is required.', $required));
                    return $this->respondRedirect('*/*/edit', ['window_id' => $windowId], true);
                }
            }

            $normalisedStart = $this->timeNormalizer->normalize($data['start_time']);
            $normalisedEnd   = $this->timeNormalizer->normalize($data['end_time']);
            if ($normalisedStart === null || $normalisedEnd === null) {
                $this->messageManager->addErrorMessage(__('Start/End times could not be understood. Try HH:MM (e.g. 09:00), or "9am", "9", "9:30 PM".'));
                return $this->respondRedirect('*/*/edit', ['window_id' => $windowId], true);
            }
            $data['start_time'] = $normalisedStart;
            $data['end_time']   = $normalisedEnd;
            if ($data['start_time'] >= $data['end_time']) {
                $this->messageManager->addErrorMessage(__('Start time must be earlier than end time.'));
                return $this->respondRedirect('*/*/edit', ['window_id' => $windowId], true);
            }

            $window->setData(array_merge($window->getData(), $data));
            $this->pickupWindowRepository->save($window);

            $this->dataPersistor->clear(self::DATA_PERSISTOR_KEY);
            $this->messageManager->addSuccessMessage(__('Pickup window saved: %1', $window->getLabel()));

            return $this->respondRedirect(
                '*/*/edit',
                ['window_id' => $window->getWindowId(), '_current' => true]
            );
        } catch (\Throwable $e) {
            $this->logger->error('ETechFlow_InStorePickup: pickup-window save failed.', ['exception' => $e->getMessage()]);
            $this->messageManager->addErrorMessage(__('Could not save pickup window: %1', $e->getMessage()));
            return $this->respondRedirect('*/*/edit', ['window_id' => $windowId], true);
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
}
