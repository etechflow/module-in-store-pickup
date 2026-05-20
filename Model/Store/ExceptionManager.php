<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\Store;

use ETechFlow\InStorePickup\Model\TimeNormalizer;
use Magento\Framework\App\ResourceConnection;

/**
 * Reader/writer for etechflow_isp_store_exception (specific-date overrides).
 *
 * Variable-cardinality: 0..N rows per store. Replace-all on save (delete then
 * insert) — safe because parent store row is unaffected and unique key
 * (store_id, exception_date) prevents duplicates.
 */
class ExceptionManager
{
    public const TABLE = 'etechflow_isp_store_exception';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly TimeNormalizer $timeNormalizer
    ) {
    }

    /**
     * @param int $storeId
     * @return array<int, array{exception_date: string, is_closed: int, open_time: ?string, close_time: ?string, reason: ?string}>
     */
    public function getRows(int $storeId): array
    {
        if ($storeId <= 0) {
            return [];
        }
        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        return $conn->fetchAll(
            $conn->select()
                 ->from($table, ['exception_date', 'is_closed', 'open_time', 'close_time', 'reason'])
                 ->where('store_id = ?', $storeId)
                 ->order('exception_date ASC')
        );
    }

    /**
     * @param int    $storeId
     * @param array<int, array{exception_date?: mixed, is_closed?: mixed, open_time?: ?string, close_time?: ?string, reason?: ?string}> $rows
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
            $seenDates = [];
            foreach ($rows as $row) {
                $date = isset($row['exception_date']) ? trim((string) $row['exception_date']) : '';
                if ($date === '' || !$this->isYmd($date)) {
                    continue;
                }
                if (isset($seenDates[$date])) {
                    continue;
                }
                $seenDates[$date] = true;

                $isClosed  = !empty($row['is_closed']) ? 1 : 0;
                $openTime  = $isClosed ? null : $this->timeNormalizer->normalize($row['open_time']  ?? null);
                $closeTime = $isClosed ? null : $this->timeNormalizer->normalize($row['close_time'] ?? null);
                $reason    = !empty($row['reason']) ? trim((string) $row['reason']) : null;

                $insert[] = [
                    'store_id'       => $storeId,
                    'exception_date' => $date,
                    'is_closed'      => $isClosed,
                    'open_time'      => $openTime,
                    'close_time'     => $closeTime,
                    'reason'         => $reason,
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

    private function isYmd(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }
}
