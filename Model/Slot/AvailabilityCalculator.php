<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model\Slot;

use ETechFlow\InStorePickup\Api\Data\StoreInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Computes available pickup dates + 1-hour slots for a store.
 *
 * v2.0 policy (per build plan agreed with Keystation):
 *
 *   - "Next day onwards (+ next 14 days)" booking window:
 *       earliest = tomorrow 00:00, latest = today + 14 days 23:59.
 *   - 1-hour slots derived from each weekday's open_time / close_time
 *     in etechflow_isp_store_hours. Closed days return empty slots.
 *   - Slot capacity is per-store (etechflow_isp_store.slot_capacity).
 *     When the count of bookings (quote + sales_order rows whose
 *     etechflow_isp_pickup_store_id + etechflow_isp_pickup_at match
 *     a given slot start) reaches capacity, the slot is reported as
 *     `available=false` but still returned so the UI can grey it.
 *   - Holidays (etechflow_isp_holiday + per-store exceptions) hide
 *     full-day-closed dates from the date list. Half-day exceptions
 *     adjust the slot range for that single date.
 *
 * Querying both quote AND sales_order means the count includes
 * customers still in checkout AND placed orders — preventing
 * double-booking when two customers grab the same slot.
 */
class AvailabilityCalculator
{
    public const BOOKING_DAYS_AHEAD = 14;
    public const BOOKING_DAYS_OFFSET = 1; // start from tomorrow

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * @return array<int, array{iso:string,label:string,weekday:int}>
     */
    public function getAvailableDates(StoreInterface $store): array
    {
        $hoursByWeekday = $this->loadStoreHours((int) $store->getId());
        $exceptionsByDate = $this->loadStoreExceptions((int) $store->getId());
        $holidaysByDate = $this->loadGlobalHolidays((int) $store->getId());

        $dates = [];
        $tz = $this->timezone->getConfigTimezone();
        $start = (new \DateTime('today', new \DateTimeZone($tz)))
            ->modify('+' . self::BOOKING_DAYS_OFFSET . ' day');
        for ($i = 0; $i < self::BOOKING_DAYS_AHEAD; $i++) {
            $day = (clone $start)->modify('+' . $i . ' day');
            $iso = $day->format('Y-m-d');
            $weekday = (int) $day->format('w'); // 0..6

            // Holiday hides the date entirely (unless an exception override)
            $exception = $exceptionsByDate[$iso] ?? null;
            if (isset($holidaysByDate[$iso]) && $exception === null) {
                continue;
            }
            if ($exception !== null && (int) $exception['is_closed'] === 1) {
                continue;
            }

            // Regular weekday closed
            $hours = $hoursByWeekday[$weekday] ?? null;
            if (!$exception && (!$hours || (int) $hours['is_closed'] === 1)) {
                continue;
            }

            $dates[] = [
                'iso' => $iso,
                'label' => $day->format('D j M'),
                'weekday' => $weekday,
            ];
        }
        return $dates;
    }

    /**
     * @return array<int, array{
     *   start:string,end:string,iso:string,available:bool,remaining:int
     * }>
     */
    public function getSlotsForDate(StoreInterface $store, string $date): array
    {
        $hoursByWeekday = $this->loadStoreHours((int) $store->getId());
        $exceptionsByDate = $this->loadStoreExceptions((int) $store->getId());
        $bookings = $this->loadBookingsByHour((int) $store->getId(), $date);
        $capacity = (int) ($store->getData('slot_capacity') ?: 10);

        $tz = $this->timezone->getConfigTimezone();
        $dayDt = new \DateTime($date, new \DateTimeZone($tz));
        $weekday = (int) $dayDt->format('w');

        $exception = $exceptionsByDate[$date] ?? null;
        $openTime = null;
        $closeTime = null;

        if ($exception !== null && (int) $exception['is_closed'] === 1) {
            return [];
        }
        if ($exception !== null && !empty($exception['open_time']) && !empty($exception['close_time'])) {
            $openTime = $exception['open_time'];
            $closeTime = $exception['close_time'];
        } else {
            $hours = $hoursByWeekday[$weekday] ?? null;
            if (!$hours || (int) $hours['is_closed'] === 1) {
                return [];
            }
            $openTime = $hours['open_time'] ?: null;
            $closeTime = $hours['close_time'] ?: null;
        }
        if (!$openTime || !$closeTime) {
            return [];
        }

        // Build 1-hour slots from open to close
        $slots = [];
        $startHour = (int) substr($openTime, 0, 2);
        $endHour = (int) substr($closeTime, 0, 2);
        for ($h = $startHour; $h < $endHour; $h++) {
            $startStr = sprintf('%02d:00', $h);
            $endStr = sprintf('%02d:00', $h + 1);
            $isoFull = sprintf('%s %s:00', $date, $startStr);
            $hourKey = sprintf('%s %s:00', $date, $startStr);
            $booked = $bookings[$hourKey] ?? 0;
            $remaining = max(0, $capacity - $booked);

            $slots[] = [
                'start' => $startStr,
                'end' => $endStr,
                'iso' => $isoFull,
                'available' => $remaining > 0,
                'remaining' => $remaining,
            ];
        }
        return $slots;
    }

    /**
     * Quick capacity re-check used by the Select controller right
     * before saving the customer's choice.
     */
    public function isSlotAvailable(StoreInterface $store, string $pickupAt): bool
    {
        // pickupAt is "YYYY-MM-DD HH:00:00"
        if (!preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2}):00:00$/', $pickupAt, $m)) {
            return false;
        }
        $slots = $this->getSlotsForDate($store, $m[1]);
        foreach ($slots as $slot) {
            if ($slot['iso'] === $pickupAt) {
                return $slot['available'];
            }
        }
        return false;
    }

    // ----------------------------------------------------------------
    // Internals
    // ----------------------------------------------------------------

    private function loadStoreHours(int $storeId): array
    {
        $conn = $this->resource->getConnection();
        $rows = $conn->fetchAll(
            $conn->select()
                ->from($this->resource->getTableName('etechflow_isp_store_hours'))
                ->where('store_id = ?', $storeId)
        );
        $by = [];
        foreach ($rows as $r) {
            $by[(int) $r['weekday']] = $r;
        }
        return $by;
    }

    private function loadStoreExceptions(int $storeId): array
    {
        $conn = $this->resource->getConnection();
        $rows = $conn->fetchAll(
            $conn->select()
                ->from($this->resource->getTableName('etechflow_isp_store_exception'))
                ->where('store_id = ?', $storeId)
        );
        $by = [];
        foreach ($rows as $r) {
            $by[(string) $r['exception_date']] = $r;
        }
        return $by;
    }

    private function loadGlobalHolidays(int $storeId): array
    {
        $conn = $this->resource->getConnection();
        // Holidays that apply globally, minus per-store opt-outs
        $rows = $conn->fetchAll(
            $conn->select()
                ->from(
                    ['h' => $this->resource->getTableName('etechflow_isp_holiday')],
                    ['holiday_date' => 'h.holiday_date']
                )
                ->joinLeft(
                    ['x' => $this->resource->getTableName('etechflow_isp_store_holiday_exclusion')],
                    'x.holiday_id = h.holiday_id AND x.store_id = ' . (int) $storeId,
                    []
                )
                ->where('x.exclusion_id IS NULL')
        );
        $by = [];
        foreach ($rows as $r) {
            $by[(string) $r['holiday_date']] = true;
        }
        return $by;
    }

    /**
     * Sums bookings (live quotes + placed orders) per hour for a date.
     *
     * Returns map: "YYYY-MM-DD HH:00:00" => int count
     */
    private function loadBookingsByHour(int $storeId, string $date): array
    {
        $conn = $this->resource->getConnection();
        $start = $date . ' 00:00:00';
        $end = $date . ' 23:59:59';

        $orderTable = $this->resource->getTableName('sales_order');
        $orderRows = $conn->fetchAll(
            $conn->select()
                ->from($orderTable, ['ts' => 'etechflow_isp_pickup_at', 'cnt' => new \Zend_Db_Expr('COUNT(*)')])
                ->where('etechflow_isp_pickup_store_id = ?', $storeId)
                ->where('etechflow_isp_pickup_at BETWEEN ? AND ?', $start, $end)
                ->group('etechflow_isp_pickup_at')
        );

        // Also count live quotes (in checkout but not yet placed) so
        // two customers can't race the same slot.
        $quoteTable = $this->resource->getTableName('quote');
        $quoteRows = $conn->fetchAll(
            $conn->select()
                ->from($quoteTable, ['ts' => 'etechflow_isp_pickup_at', 'cnt' => new \Zend_Db_Expr('COUNT(*)')])
                ->where('etechflow_isp_pickup_store_id = ?', $storeId)
                ->where('etechflow_isp_pickup_at BETWEEN ? AND ?', $start, $end)
                ->where('is_active = ?', 1)
                ->where('reserved_order_id IS NULL') // exclude already-converted quotes
                ->group('etechflow_isp_pickup_at')
        );

        $by = [];
        foreach ($orderRows as $r) {
            $by[(string) $r['ts']] = ($by[(string) $r['ts']] ?? 0) + (int) $r['cnt'];
        }
        foreach ($quoteRows as $r) {
            $by[(string) $r['ts']] = ($by[(string) $r['ts']] ?? 0) + (int) $r['cnt'];
        }
        return $by;
    }
}
