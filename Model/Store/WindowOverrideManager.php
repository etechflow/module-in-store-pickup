<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\Store;

use Magento\Framework\App\ResourceConnection;

/**
 * Reader/writer for etechflow_isp_store_pickup_window (per-store window overrides).
 *
 * Default behaviour: a store offers every active pickup window at the global
 * capacity. Override rows only exist to *disable* a window for one store or
 * to set a custom capacity. So an empty row set means "no overrides — all
 * windows behave at default."
 */
class WindowOverrideManager
{
    public const TABLE = 'etechflow_isp_store_pickup_window';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @param int $storeId
     * @return array<int, array{window_id: int, is_disabled: int, capacity_override: ?int}>
     */
    public function getRows(int $storeId): array
    {
        if ($storeId <= 0) {
            return [];
        }
        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $rows  = $conn->fetchAll(
            $conn->select()
                 ->from($table, ['window_id', 'is_disabled', 'capacity_override'])
                 ->where('store_id = ?', $storeId)
                 ->order('window_id ASC')
        );
        foreach ($rows as &$r) {
            $r['window_id']         = (int) $r['window_id'];
            $r['is_disabled']       = (int) $r['is_disabled'];
            $r['capacity_override'] = $r['capacity_override'] !== null ? (int) $r['capacity_override'] : null;
        }
        return $rows;
    }

    /**
     * @param int    $storeId
     * @param array<int, array{window_id?: mixed, is_disabled?: mixed, capacity_override?: mixed}> $rows
     * @return void
     */
    public function replaceRows(int $storeId, array $rows): void
    {
        if ($storeId <= 0) {
            return;
        }
        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);

        $conn->beginTransaction();
        try {
            $conn->delete($table, ['store_id = ?' => $storeId]);

            $insert = [];
            $seenWindows = [];
            foreach ($rows as $row) {
                $windowId = (int) ($row['window_id'] ?? 0);
                if ($windowId <= 0 || isset($seenWindows[$windowId])) {
                    continue;
                }
                $seenWindows[$windowId] = true;

                $isDisabled = !empty($row['is_disabled']) ? 1 : 0;
                $cap = $row['capacity_override'] ?? null;
                $capacityOverride = ($cap === null || $cap === '') ? null : (int) $cap;

                // Skip no-op rows (default everywhere): saves DB churn.
                if ($isDisabled === 0 && $capacityOverride === null) {
                    continue;
                }

                $insert[] = [
                    'store_id'          => $storeId,
                    'window_id'         => $windowId,
                    'is_disabled'       => $isDisabled,
                    'capacity_override' => $capacityOverride,
                ];
            }
            if ($insert) {
                $conn->insertMultiple($table, $insert);
            }
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }
}
