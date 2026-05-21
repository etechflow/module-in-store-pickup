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
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * POST handler for the store edit form.
 *
 * v1.1.2 fix: Magento UI Component forms submit via AJAX. Returning a
 * plain HTTP 302 Redirect causes the AJAX library to silently follow
 * the redirect (fetching the new page HTML behind the scenes) without
 * the browser actually navigating — leaving the user on the same edit
 * URL with the form cleared by the UI's reset-on-success behaviour.
 *
 * Fix: detect AJAX submits via `isAjax()` / `X-Requested-With` and
 * return ResultJson with a proper `{ error, messages, redirect, back }`
 * shape that Magento's `Magento_Ui/js/form/save` handler understands.
 * Plain (non-AJAX) submits keep the original Redirect behaviour for
 * backwards compatibility.
 *
 * Also: persist form data via DataPersistor on save failure so the
 * customer's typing doesn't get lost between attempts.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::stores';

    /** Session key used by Ui\Component\Form\DataProvider to rehydrate. */
    private const DATA_PERSISTOR_KEY = 'etechflow_isp_store';

    public function __construct(
        Context $context,
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly StoreFactory $storeFactory,
        private readonly AssignmentManager $assignmentManager,
        private readonly HoursManager $hoursManager,
        private readonly ExceptionManager $exceptionManager,
        private readonly WindowOverrideManager $windowOverrideManager,
        private readonly TimeNormalizer $timeNormalizer,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly JsonFactory $jsonFactory,
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
        $storeId = (int) ($data['store_id'] ?? 0);

        // v1.1.6 fix: the UI Component form posts store_id=0 for new stores.
        // If we leave that in $data, the subsequent `$store->setData($data)`
        // copies store_id=0 onto the entity, and Magento's AbstractModel
        // treats it as an EXISTING row with PK=0 — emitting
        //   UPDATE etechflow_isp_store SET ... WHERE store_id=0
        // which matches 0 rows, no exception, no insert. The controller
        // STILL hits its success branch (because save() didn't throw),
        // shows "Store saved", and redirects to /edit/store_id/0/ —
        // where the form re-renders blank. Customer's words: "It said
        // saved but the form is empty".
        //
        // Strip it here so the new-store path actually inserts. For an
        // existing store, $storeId still holds the int — we use that
        // separately to load the row above this point.
        unset($data['store_id']);

        try {
            // Persist form data BEFORE we touch the DB — if anything later
            // throws, the DataProvider rehydrates the form from this on
            // the next page load.
            $this->dataPersistor->set(self::DATA_PERSISTOR_KEY, $data);

            // Required fields
            if (empty($data['code'])) {
                $this->messageManager->addErrorMessage(__('Store code is required.'));
                return $this->respondRedirect('*/*/edit', ['store_id' => $storeId], true);
            }
            if (empty($data['name'])) {
                $this->messageManager->addErrorMessage(__('Store name is required.'));
                return $this->respondRedirect('*/*/edit', ['store_id' => $storeId], true);
            }

            // Load existing OR create new
            if ($storeId > 0) {
                $store = $this->storeRepository->getById($storeId);
            } else {
                $store = $this->storeFactory->create();
            }

            // Uniqueness check for code (only when changed or new)
            if ($storeId === 0 || $store->getCode() !== $data['code']) {
                try {
                    $existing = $this->storeRepository->getByCode($data['code']);
                    if ($existing->getStoreId() !== $storeId) {
                        $this->messageManager->addErrorMessage(__('A store with this code already exists.'));
                        return $this->respondRedirect('*/*/edit', ['store_id' => $storeId], true);
                    }
                } catch (NoSuchEntityException $e) {
                    // No existing store with that code — fine
                }
            }

            $amenityIds      = $data['assigned_amenity_ids'] ?? null;
            $tagIds          = $data['assigned_tag_ids']     ?? null;
            // v1.1.11 fix: dynamicRows posts its rows nested under its own
            // dataScope key — {"exceptions": [...rows]} — rather than as a
            // flat array. The previous shape check treated the outer dict as
            // the row list, so $row['window_id'] / $row['exception_date'] were
            // undefined and ReplaceRows silently skipped every row.
            $exceptionsRows  = $this->unwrapDynamicRows($data['exceptions']       ?? null, 'exceptions');
            $windowOverrides = $this->unwrapDynamicRows($data['window_overrides'] ?? null, 'window_overrides');
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

            // Save succeeded — clear the persisted form data so the next
            // form load shows the DB state, not the just-typed values.
            $this->dataPersistor->clear(self::DATA_PERSISTOR_KEY);

            $this->messageManager->addSuccessMessage(__('Store saved: %1', $store->getName()));

            // Default behaviour: after a successful Save, return the
            // customer to the EDIT form for the just-saved store. This
            // way they immediately see their saved data + can verify it.
            // (Previous behaviour was to redirect to the listing, but
            // that gives no visible feedback that the data persisted.)
            //
            // The user can click Back/Cancel to return to the listing.
            return $this->respondRedirect(
                '*/*/edit',
                ['store_id' => $store->getStoreId(), '_current' => true]
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'ETechFlow_InStorePickup: store save failed.',
                ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
            );
            $this->messageManager->addErrorMessage(__('Could not save store: %1', $e->getMessage()));
            return $this->respondRedirect('*/*/edit', ['store_id' => $storeId], true);
        }
    }

    /**
     * AJAX-aware redirect.
     *
     * If the request was made via AJAX (UI Component form button-adapter
     * does this), return ResultJson with the redirect URL baked in.
     * Otherwise return a plain Redirect for legacy form submits.
     *
     * @param string                    $path
     * @param array<string, mixed>      $params
     * @param bool                      $isError If true, include error flag for the JS.
     */
    private function respondRedirect(string $path, array $params = [], bool $isError = false): ResultInterface
    {
        $url = $this->_url->getUrl($path, $params);

        if ($this->isAjaxRequest()) {
            /** @var JsonResult $json */
            $json = $this->jsonFactory->create();
            return $json->setData([
                'redirect' => $url,
                'error'    => $isError,
                'back'     => false,
            ]);
        }

        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();
        return $redirect->setPath($path, $params);
    }

    private function isAjaxRequest(): bool
    {
        $request = $this->getRequest();
        return $request->isAjax()
            || $request->isXmlHttpRequest()
            || strtolower((string) $request->getHeader('X-Requested-With')) === 'xmlhttprequest';
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
     * Unwrap one level of dataScope-nested serialisation from a Magento
     * UI Component dynamicRows component.
     *
     * The component sometimes posts its rows as `{"<scope>": [...rows]}`
     * instead of a flat array, depending on how the parent fieldset's
     * dataScope is wired. Returns a flat rows array (or null if the
     * payload was missing/empty).
     *
     * @param mixed  $data
     * @param string $key
     * @return array<int, mixed>|null
     */
    private function unwrapDynamicRows($data, string $key): ?array
    {
        if (!is_array($data) || empty($data)) {
            return null;
        }
        if (isset($data[$key]) && is_array($data[$key])) {
            return $data[$key];
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
