<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Holiday;

use ETechFlow\InStorePickup\Api\HolidayRepositoryInterface;
use ETechFlow\InStorePickup\Controller\Adminhtml\AjaxSaveResultTrait;
use ETechFlow\InStorePickup\Model\HolidayFactory;
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

    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::holidays';

    private const DATA_PERSISTOR_KEY = 'etechflow_isp_holiday';

    public function __construct(
        Context $context,
        private readonly HolidayRepositoryInterface $holidayRepository,
        private readonly HolidayFactory $holidayFactory,
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
        $holidayId = (int) ($data['holiday_id'] ?? 0);

        try {
            $this->dataPersistor->set(self::DATA_PERSISTOR_KEY, $data);

            $holiday = $holidayId > 0
                ? $this->holidayRepository->getById($holidayId)
                : $this->holidayFactory->create();

            if (empty($data['name'])) {
                $this->messageManager->addErrorMessage(__('Holiday name is required.'));
                return $this->respondRedirect('*/*/edit', ['holiday_id' => $holidayId], true);
            }
            if (empty($data['holiday_date'])) {
                $this->messageManager->addErrorMessage(__('Holiday date is required.'));
                return $this->respondRedirect('*/*/edit', ['holiday_id' => $holidayId], true);
            }

            if (!$data['is_closed']) {
                $data['reduced_open']  = $this->timeNormalizer->normalize($data['reduced_open']  ?? null);
                $data['reduced_close'] = $this->timeNormalizer->normalize($data['reduced_close'] ?? null);
                if ($data['reduced_open'] === null || $data['reduced_close'] === null) {
                    $this->messageManager->addErrorMessage(
                        __('When "Closed All Day" is off, enter both Reduced Open and Reduced Close (e.g. "10:00" and "14:00" — "10am" and "2 PM" also work).')
                    );
                    return $this->respondRedirect('*/*/edit', ['holiday_id' => $holidayId], true);
                }
                if ($data['reduced_open'] >= $data['reduced_close']) {
                    $this->messageManager->addErrorMessage(__('Reduced-open must be earlier than reduced-close.'));
                    return $this->respondRedirect('*/*/edit', ['holiday_id' => $holidayId], true);
                }
            } else {
                $data['reduced_open']  = null;
                $data['reduced_close'] = null;
            }

            $holiday->setData(array_merge($holiday->getData(), $data));
            $this->holidayRepository->save($holiday);

            $this->dataPersistor->clear(self::DATA_PERSISTOR_KEY);
            $this->messageManager->addSuccessMessage(__('Holiday saved: %1', $holiday->getName()));

            return $this->respondRedirect(
                '*/*/edit',
                ['holiday_id' => $holiday->getHolidayId(), '_current' => true]
            );
        } catch (\Throwable $e) {
            $this->logger->error('ETechFlow_InStorePickup: holiday save failed.', ['exception' => $e->getMessage()]);
            $this->messageManager->addErrorMessage(__('Could not save holiday: %1', $e->getMessage()));
            return $this->respondRedirect('*/*/edit', ['holiday_id' => $holidayId], true);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalisePayload(array $data): array
    {
        foreach (['name', 'holiday_date', 'reduced_open', 'reduced_close', 'country_code'] as $f) {
            if (isset($data[$f]) && is_string($data[$f])) {
                $data[$f] = trim($data[$f]);
            }
        }
        if (!empty($data['country_code'])) {
            $data['country_code'] = strtoupper($data['country_code']);
        }
        $data['is_recurring'] = !empty($data['is_recurring']) ? 1 : 0;
        $data['is_closed']    = !empty($data['is_closed']) ? 1 : 0;
        return $data;
    }
}
