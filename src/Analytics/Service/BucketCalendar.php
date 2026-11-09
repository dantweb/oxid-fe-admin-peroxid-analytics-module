<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Service;

use DateTimeImmutable;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsGranularity;

/**
 * Bucket keys: `YYYY-MM-DD` of the day, or of the Monday that starts the ISO week. The keys of a period are
 * complete and ordered, so a bucket without data is still in the answer (as zeros).
 */
final class BucketCalendar
{
    public const DAY_FORMAT = 'Y-m-d';

    public function keyOf(DateTimeImmutable $moment, AdminAnalyticsGranularity $granularity): string
    {
        $day = $moment->setTime(0, 0);
        if ($granularity === AdminAnalyticsGranularity::WEEKLY) {
            $day = $this->mondayOf($day);
        }

        return $day->format(self::DAY_FORMAT);
    }

    /**
     * @return list<string>
     */
    public function keysOf(AnalyticsRange $range, AdminAnalyticsGranularity $granularity): array
    {
        $step = $granularity === AdminAnalyticsGranularity::WEEKLY ? '+7 days' : '+1 day';
        $cursor = $granularity === AdminAnalyticsGranularity::WEEKLY ? $this->mondayOf($range->from()) : $range->from();
        $keys = [];
        while ($cursor <= $range->to()) {
            $keys[] = $cursor->format(self::DAY_FORMAT);
            $cursor = $cursor->modify($step);
        }

        return $keys;
    }

    private function mondayOf(DateTimeImmutable $day): DateTimeImmutable
    {
        $offset = ((int) $day->format('N')) - 1; // ISO: Monday = 1

        return $offset === 0 ? $day : $day->modify('-' . $offset . ' days');
    }
}
