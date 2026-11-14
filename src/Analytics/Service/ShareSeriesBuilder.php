<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Service;

use DateTimeImmutable;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsGranularity;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRankMeasure;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsShareBucket;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsShareSeries;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\ShareDayFigures;

/**
 * Lays the per-day figures of the top products over the complete calendar: an empty bucket is a zero share, days of an
 * ISO week fold into their Monday, and a share is always computed from the sums of its bucket.
 */
final class ShareSeriesBuilder
{
    public function __construct(
        private readonly BucketCalendar $calendar,
        private readonly ShareRounder $rounder
    ) {
    }

    /**
     * @param list<ShareDayFigures> $days
     */
    public function build(
        AnalyticsRange $range,
        AdminAnalyticsGranularity $granularity,
        AdminAnalyticsRankMeasure $measure,
        array $days
    ): AdminAnalyticsShareSeries {
        $sums = [];
        foreach ($this->calendar->keysOf($range, $granularity) as $key) {
            $sums[$key] = [0.0, 0.0];
        }
        $whole = [0.0, 0.0];
        foreach ($days as $day) {
            $key = $this->calendar->keyOf(new DateTimeImmutable($day->day), $granularity);
            if (!isset($sums[$key])) {
                continue; // a row outside the calendar never reaches the answer
            }
            $sums[$key] = [$sums[$key][0] + $day->top, $sums[$key][1] + $day->total];
            $whole = [$whole[0] + $day->top, $whole[1] + $day->total];
        }
        $buckets = [];
        foreach ($sums as $key => [$top, $total]) {
            $buckets[] = $this->bucket((string) $key, $top, $total);
        }

        return new AdminAnalyticsShareSeries(
            $range->from(),
            $range->to(),
            $granularity,
            $measure,
            $buckets,
            $this->bucket(AnalyticsReader::TOTALS_KEY, $whole[0], $whole[1])
        );
    }

    private function bucket(string $key, float $top, float $total): AdminAnalyticsShareBucket
    {
        $share = $this->rounder->percentages([$top, max(0.0, $total - $top)], $total)[0];

        return new AdminAnalyticsShareBucket($key, round($top, 2), round($total, 2), $share);
    }
}
