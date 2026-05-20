<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model;

/**
 * Parse free-form time strings into canonical "HH:MM" 24-hour format.
 *
 * The admin form fields where merchants enter opening/closing times are
 * <input type="text"> with a placeholder of "HH:MM". In practice merchants
 * type anything: "9", "9am", "9:00", "9 PM", "21:30". Rather than rejecting
 * everything but the strictest form, this normalizer accepts the obvious
 * variants and converts them. Empty input returns null (so the storage
 * layer can NULL out the column when the field was left blank).
 *
 * Accepted inputs (case-insensitive, whitespace-tolerant):
 *   9             → 09:00
 *   9am / 9 AM    → 09:00
 *   9pm / 9 PM    → 21:00
 *   9:30          → 09:30
 *   9:30 am       → 09:30
 *   9:30 pm       → 21:30
 *   09:00         → 09:00 (passthrough)
 *   21:30         → 21:30 (passthrough)
 *
 * Invalid input (e.g. "lunchtime", "25:00", "9:99") returns null. Callers
 * can distinguish "user left blank" from "user typed garbage" only via
 * whether the trimmed input was empty — both return null here, which is
 * fine because both should produce a NULL column.
 */
class TimeNormalizer
{
    /**
     * @param string|null $raw
     * @return string|null Canonical "HH:MM" or null when input was blank or unparseable.
     */
    public function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        $value = strtolower(str_replace(' ', '', $trimmed));

        // Already canonical?
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value, $m)) {
            return $m[1] . ':' . $m[2];
        }

        // Hour only with am/pm: "9am", "12pm"
        if (preg_match('/^(\d{1,2})(am|pm)$/', $value, $m)) {
            return $this->buildFromAmPm((int) $m[1], 0, $m[2]);
        }

        // Hour+minute with am/pm: "9:30am", "12:15pm"
        if (preg_match('/^(\d{1,2}):(\d{2})(am|pm)$/', $value, $m)) {
            return $this->buildFromAmPm((int) $m[1], (int) $m[2], $m[3]);
        }

        // Hour only no am/pm — assume 24h: "9" → 09:00, "21" → 21:00
        if (preg_match('/^(\d{1,2})$/', $value, $m)) {
            $hour = (int) $m[1];
            if ($hour < 0 || $hour > 23) {
                return null;
            }
            return sprintf('%02d:00', $hour);
        }

        // Hour+minute no am/pm but with extra cleanup needed: "9:30" → 09:30
        if (preg_match('/^(\d{1,2}):(\d{1,2})$/', $value, $m)) {
            $hour   = (int) $m[1];
            $minute = (int) $m[2];
            if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
                return null;
            }
            return sprintf('%02d:%02d', $hour, $minute);
        }

        return null;
    }

    private function buildFromAmPm(int $hour, int $minute, string $ampm): ?string
    {
        if ($hour < 1 || $hour > 12 || $minute < 0 || $minute > 59) {
            return null;
        }
        if ($ampm === 'am') {
            $hour = ($hour === 12) ? 0 : $hour;
        } else {
            $hour = ($hour === 12) ? 12 : $hour + 12;
        }
        return sprintf('%02d:%02d', $hour, $minute);
    }
}
