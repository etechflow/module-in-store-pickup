<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\Store;

use Magento\Framework\App\ResourceConnection;

/**
 * Generic store ↔ N pivot reader/writer.
 *
 * One service handles Tag / Amenity assignment (and any future pivot table
 * keyed on (store_id, X_id)). All pivots share the schema:
 *
 *   - PK auto-increment
 *   - FK store_id (CASCADE)
 *   - FK <related>_id (CASCADE)
 *   - UNIQUE (store_id, <related>_id)
 *
 * Reads return an int[] of related IDs.
 * Writes are diff-based (insert new, delete removed) so admin saves are
 * idempotent and don't churn FK history.
 */
class AssignmentManager
{
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @param string $tableName  Pivot table (e.g. etechflow_isp_store_amenity)
     * @param string $relatedKey FK column on the pivot (e.g. amenity_id)
     * @param int    $storeId
     * @return int[]
     */
    public function getAssigned(string $tableName, string $relatedKey, int $storeId): array
    {
        if ($storeId <= 0) {
            return [];
        }
        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName($tableName);
        $select = $conn->select()
            ->from($table, [$relatedKey])
            ->where('store_id = ?', $storeId);
        return array_map('intval', $conn->fetchCol($select));
    }

    /**
     * Diff-replace assignment: insert missing, delete removed.
     *
     * @param string $tableName
     * @param string $relatedKey
     * @param int    $storeId
     * @param int[]  $newIds
     * @return void
     */
    public function setAssigned(string $tableName, string $relatedKey, int $storeId, array $newIds): void
    {
        if ($storeId <= 0) {
            return;
        }
        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName($tableName);

        $newIds = array_values(array_unique(array_filter(array_map('intval', $newIds))));
        $current = $this->getAssigned($tableName, $relatedKey, $storeId);

        $toInsert = array_diff($newIds, $current);
        $toDelete = array_diff($current, $newIds);

        if ($toDelete) {
            $conn->delete($table, [
                'store_id = ?'           => $storeId,
                $relatedKey . ' IN (?)'  => $toDelete,
            ]);
        }
        if ($toInsert) {
            $rows = [];
            foreach ($toInsert as $id) {
                $rows[] = ['store_id' => $storeId, $relatedKey => $id];
            }
            $conn->insertMultiple($table, $rows);
        }
    }
}
