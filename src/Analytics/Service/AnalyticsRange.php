<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Service;

use DateTimeImmutable;
use DateTimeInterface;
use Dantweb\PeroxidAnalytics\Analytics\Exception\AnalyticsRangeException;

/**
 * The period of a query, normalised to whole days: `from` at midnight, `until` the midnight after `to`
 * (exclusive), so a row of the last day counts. At most a year (366 days), `to` not before `from`.
 */
final class AnalyticsRange
{
    public const MAX_DAYS = 366;

    private function __construct(
        private readonly DateTimeImmutable $from,
        private readonly DateTimeImmutable $to
    ) {
    }

    public static function of(DateTimeInterface $from, DateTimeInterface $to): self
    {
        $first = DateTimeImmutable::createFromInterface($from)->setTime(0, 0);
        $last = DateTimeImmutable::createFromInterface($to)->setTime(0, 0);
        if ($last < $first) {
            throw AnalyticsRangeException::toBeforeFrom();
        }
        $range = new self($first, $last);
        if ($range->days() > self::MAX_DAYS) {
            throw AnalyticsRangeException::tooLong(self::MAX_DAYS);
        }

        return $range;
    }

    /** The first day, midnight. */
    public function from(): DateTimeImmutable
    {
        return $this->from;
    }

    /** The last day, midnight. */
    public function to(): DateTimeImmutable
    {
        return $this->to;
    }

    /** The midnight after the last day: rows strictly before it belong to the period. */
    public function until(): DateTimeImmutable
    {
        return $this->to->modify('+1 day');
    }

    public function days(): int
    {
        return (int) $this->from->diff($this->to)->format('%a') + 1;
    }
}
