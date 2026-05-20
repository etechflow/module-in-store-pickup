<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\Store;

use Magento\Framework\App\ResourceConnection;

/**
 * Reader/writer for etechflow_isp_store_hours.
 *
 * Schema invariant: exactly 7 rows per store (one per weekday, 0=Sun..6=Sat).
 * - getRows() always returns 7 rows. Missing weekdays default to closed.
 * - replaceRows() wipes existing rows for the store then inserts the supplied 7.
 *   This is simpler than diff-replace and safe because the parent store row
 *   is unchanged — only child rows churn.
 */
class HoursManager
{
    public const WEEKDAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public const TABLE = 'etechflow_isp_store_hours';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @param int $storeId
     * @return array<int, array{is_closed: int, open_time: ?string, close_time: ?string}>
     *               Keyed by weekday 0..6, always exactly 7 entries.
     */
    public function getRows(int $storeId): array
    {
        $rows = $this->blank();
        if ($storeId <= 0) {
            return $rows;
        }
        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $existing = $conn->fetchAll(
            $conn->select()
                 ->from($table, ['weekday', 'is_closed', 'open_time', 'close_time'])
                 ->where('store_id = ?', $storeId)
        );
        foreach ($existing as $row) {
            $weekday = (int) $row['weekday'];
            if (!isset($rows[$weekday])) {
                continue;
            }
            $rows[$weekday] = [
                'is_closed'  => (int) $row['is_closed'],
                'open_time'  => $row['open_time'],
                'close_time' => $row['close_time'],
            ];
        }
        return $rows;
    }

    /**
     * @param int   $storeId
     * @param array<int, array{is_closed?: mixed, open_time?: ?string, close_time?: ?string}> $rows
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
            foreach (array_keys(self::WEEKDAYS) as $weekday) {
                $row = $rows[$weekday] ?? [];
                $isClosed  = !empty($row['is_closed']) ? 1 : 0;
                $openTime  = $isClosed ? null : ($row['open_time']  ?: null);
                $closeTime = $isClosed ? null : ($row['close_time'] ?: null);
                $insert[] = [
                    'store_id'   => $storeId,
                    'weekday'    => $weekday,
                    'is_closed'  => $isClosed,
                    'open_time'  => $openTime,
                    'close_time' => $closeTime,
                ];
            }
            $conn->insertMultiple($table, $insert);
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * @return array<int, array{is_closed: int, open_time: ?string, close_time: ?string}>
     */
    private function blank(): array
    {
        $out = [];
        foreach (array_keys(self::WEEKDAYS) as $weekday) {
            $out[$weekday] = ['is_closed' => 1, 'open_time' => null, 'close_time' => null];
        }
        return $out;
    }
}
