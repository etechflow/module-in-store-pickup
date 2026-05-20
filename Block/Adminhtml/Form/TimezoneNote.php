<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Block\Adminhtml\Form;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Renders a friendly "all times below are in <tz>" hint inside admin
 * forms that take HH:MM input. Lives at the top of any fieldset
 * containing opening hours / window times / exception times — wired in
 * via <htmlContent> in the form's ui_component XML.
 *
 * Reads the active timezone from Magento's TimezoneInterface, so it
 * follows whatever the merchant set under Stores → Configuration →
 * General → Locale Options. Includes the current UTC offset so a
 * merchant can sanity-check ("we're in London, UTC+00 — that's right").
 */
class TimezoneNote extends Template
{
    /** @var string */
    protected $_template = 'ETechFlow_InStorePickup::form/timezone-note.phtml';

    public function __construct(
        Context $context,
        private readonly TimezoneInterface $timezone,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return string e.g. "Europe/London"
     */
    public function getTimezoneId(): string
    {
        return (string) $this->timezone->getConfigTimezone();
    }

    /**
     * @return string e.g. "UTC+00:00" or "UTC-05:00"
     */
    public function getUtcOffset(): string
    {
        try {
            $tz = new \DateTimeZone($this->getTimezoneId());
            $offsetSeconds = $tz->getOffset(new \DateTimeImmutable('now', $tz));
            $sign  = $offsetSeconds >= 0 ? '+' : '-';
            $abs   = abs($offsetSeconds);
            $hours = intdiv($abs, 3600);
            $mins  = intdiv($abs % 3600, 60);
            return sprintf('UTC%s%02d:%02d', $sign, $hours, $mins);
        } catch (\Throwable $e) {
            return 'UTC+00:00';
        }
    }

    /**
     * @return string Current wall-clock time in the configured TZ, for "you typed 09:00, it's 14:23 here right now"-style sanity checks.
     */
    public function getNowInTimezone(): string
    {
        return $this->timezone->date()->format('Y-m-d H:i');
    }
}
