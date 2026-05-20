<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Store;

use ETechFlow\InStorePickup\Api\StoreRepositoryInterface;
use ETechFlow\InStorePickup\Model\Store\AssignmentManager;
use ETechFlow\InStorePickup\Model\Store\ExceptionManager;
use ETechFlow\InStorePickup\Model\Store\HoursManager;
use ETechFlow\InStorePickup\Model\Store\WindowOverrideManager;
use ETechFlow\InStorePickup\Model\StoreFactory;
use ETechFlow\InStorePickup\Model\TimeNormalizer;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * POST handler for the store edit form.
 *
 * Trim everything, validate code uniqueness, persist via repository.
 * Falls back to the form (with error) on validation failure.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::stores';

    public function __construct(
        Context $context,
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly StoreFactory $storeFactory,
        private readonly AssignmentManager $assignmentManager,
        private readonly HoursManager $hoursManager,
        private readonly ExceptionManager $exceptionManager,
        private readonly WindowOverrideManager $windowOverrideManager,
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
            // Load existing OR create new
            $storeId = (int) ($data['store_id'] ?? 0);
            if ($storeId > 0) {
                $store = $this->storeRepository->getById($storeId);
            } else {
                $store = $this->storeFactory->create();
            }

            // Required fields
            if (empty($data['code'])) {
                $this->messageManager->addErrorMessage(__('Store code is required.'));
                return $redirect->setPath('*/*/edit', ['store_id' => $storeId]);
            }
            if (empty($data['name'])) {
                $this->messageManager->addErrorMessage(__('Store name is required.'));
                return $redirect->setPath('*/*/edit', ['store_id' => $storeId]);
            }

            // Uniqueness check for code (only when changed or new)
            if ($storeId === 0 || $store->getCode() !== $data['code']) {
                try {
                    $existing = $this->storeRepository->getByCode($data['code']);
                    if ($existing->getStoreId() !== $storeId) {
                        $this->messageManager->addErrorMessage(__('A store with this code already exists.'));
                        return $redirect->setPath('*/*/edit', ['store_id' => $storeId]);
                    }
                } catch (NoSuchEntityException $e) {
                    // No existing store with that code — fine
                }
            }

            $amenityIds      = $data['assigned_amenity_ids'] ?? null;
            $tagIds          = $data['assigned_tag_ids']     ?? null;
            $exceptionsRows  = isset($data['exceptions'])       && is_array($data['exceptions'])       ? $data['exceptions']       : null;
            $windowOverrides = isset($data['window_overrides']) && is_array($data['window_overrides']) ? $data['window_overrides'] : null;
            $hoursRows       = $this->extractHoursRows($data);
            unset(
                $data['assigned_amenity_ids'],
                $data['assigned_tag_ids'],
                $data['exceptions'],
                $data['window_overrides']
            );
            foreach (array_keys(HoursManager::WEEKDAYS) as $weekday) {
                unset(
                    $data['hours_' . $weekday . '_is_closed'],
                    $data['hours_' . $weekday . '_open_time'],
                    $data['hours_' . $weekday . '_close_time']
                );
            }

            $store->setData(array_merge($store->getData(), $data));
            $this->storeRepository->save($store);

            if (is_array($amenityIds)) {
                $this->assignmentManager->setAssigned(
                    'etechflow_isp_store_amenity',
                    'amenity_id',
                    (int) $store->getStoreId(),
                    $amenityIds
                );
            }
            if (is_array($tagIds)) {
                $this->assignmentManager->setAssigned(
                    'etechflow_isp_store_tag',
                    'tag_id',
                    (int) $store->getStoreId(),
                    $tagIds
                );
            }
            if ($hoursRows !== null) {
                $this->hoursManager->replaceRows((int) $store->getStoreId(), $hoursRows);
            }
            if ($exceptionsRows !== null) {
                $this->exceptionManager->replaceRows((int) $store->getStoreId(), $exceptionsRows);
            }
            if ($windowOverrides !== null) {
                $this->windowOverrideManager->replaceRows((int) $store->getStoreId(), $windowOverrides);
            }

            $this->messageManager->addSuccessMessage(__('Store saved: %1', $store->getName()));

            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['store_id' => $store->getStoreId()]);
            }
            return $redirect->setPath('*/*/index');
        } catch (\Throwable $e) {
            $this->logger->error('ETechFlow_InStorePickup: store save failed.', ['exception' => $e->getMessage()]);
            $this->messageManager->addErrorMessage(__('Could not save store: %1', $e->getMessage()));
            return $redirect->setPath('*/*/edit', ['store_id' => (int) ($data['store_id'] ?? 0)]);
        }
    }

    /**
     * Trim strings, normalise boolean checkboxes, drop empties.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalisePayload(array $data): array
    {
        $stringFields = [
            'code', 'name', 'description', 'pickup_instructions', 'street',
            'city', 'region', 'postcode', 'country_code', 'phone', 'email',
            'manager_name', 'image', 'msi_source_code',
        ];
        foreach ($stringFields as $f) {
            if (isset($data[$f]) && is_string($data[$f])) {
                $data[$f] = trim($data[$f]);
            }
        }

        $data['is_active']   = !empty($data['is_active']) ? 1 : 0;
        $data['sort_order']  = (int) ($data['sort_order'] ?? 0);

        if (isset($data['latitude']) && $data['latitude'] !== '') {
            $data['latitude'] = (float) $data['latitude'];
        } else {
            $data['latitude'] = null;
        }
        if (isset($data['longitude']) && $data['longitude'] !== '') {
            $data['longitude'] = (float) $data['longitude'];
        } else {
            $data['longitude'] = null;
        }

        return $data;
    }

    /**
     * Pull the 7 flat weekday fields out of the POST and shape them as
     * rows keyed by weekday 0..6. Returns null if no hours fields were
     * submitted at all (so a partial save doesn't wipe existing rows).
     *
     * @param array<string, mixed> $data
     * @return array<int, array{is_closed: int, open_time: ?string, close_time: ?string}>|null
     */
    private function extractHoursRows(array $data): ?array
    {
        $present = false;
        foreach (array_keys(HoursManager::WEEKDAYS) as $weekday) {
            if (array_key_exists('hours_' . $weekday . '_is_closed', $data)
                || array_key_exists('hours_' . $weekday . '_open_time', $data)
                || array_key_exists('hours_' . $weekday . '_close_time', $data)
            ) {
                $present = true;
                break;
            }
        }
        if (!$present) {
            return null;
        }

        $rows = [];
        foreach (array_keys(HoursManager::WEEKDAYS) as $weekday) {
            $isClosed = !empty($data['hours_' . $weekday . '_is_closed']) ? 1 : 0;
            $rows[$weekday] = [
                'is_closed'  => $isClosed,
                'open_time'  => $this->timeNormalizer->normalize($data['hours_' . $weekday . '_open_time']  ?? null),
                'close_time' => $this->timeNormalizer->normalize($data['hours_' . $weekday . '_close_time'] ?? null),
            ];
        }
        return $rows;
    }
}
