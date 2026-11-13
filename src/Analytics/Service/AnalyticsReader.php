<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Service;

use DateTimeImmutable;
use DateTimeInterface;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsBucket;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsGranularity;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsOrderCounts;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsSeries;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\AnalyticsRepositoryInterface;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\ShopCurrencyInterface;

/**
 * Lays the per-day figures of the repository over the complete calendar of the period: every bucket of the
 * period is in the answer, days of one ISO week fold into the Monday's bucket, the totals are the sums (and the
 * ratios of the sums).
 */
final class AnalyticsReader implements AnalyticsReaderInterface
{
    public const TOTALS_KEY = 'total';

    public function __construct(
        private readonly AnalyticsRepositoryInterface $repository,
        private readonly ShopCurrencyInterface $currency,
        private readonly BucketCalendar $calendar
    ) {
    }

    public function series(
        DateTimeInterface $from,
        DateTimeInterface $to,
        AdminAnalyticsGranularity $granularity
    ): AdminAnalyticsSeries {
        $range = AnalyticsRange::of($from, $to);
        $buckets = [];
        foreach ($this->calendar->keysOf($range, $granularity) as $key) {
            $buckets[$key] = AdminAnalyticsBucket::empty($key);
        }
        $totals = AdminAnalyticsBucket::empty(self::TOTALS_KEY);
        foreach ($this->repository->perDay($range) as $figures) {
            $key = $this->calendar->keyOf(new DateTimeImmutable($figures->day), $granularity);
            if (!isset($buckets[$key])) {
                continue; // a row outside the calendar never reaches the answer
            }
            $buckets[$key] = $buckets[$key]->plus($figures->orders, $figures->sales, $figures->customers);
            $totals = $totals->plus($figures->orders, $figures->sales, $figures->customers);
        }

        return new AdminAnalyticsSeries(
            $range->from(),
            $range->to(),
            $granularity,
            $this->currency->code(),
            array_values($buckets),
            $totals
        );
    }

    public function orderCounts(DateTimeInterface $from, DateTimeInterface $to): AdminAnalyticsOrderCounts
    {
        $range = AnalyticsRange::of($from, $to);
        $counts = $this->repository->orderCounts($range);

        return new AdminAnalyticsOrderCounts(
            $range->from(),
            $range->to(),
            $counts->total,
            $counts->paid,
            $counts->shipped,
            $counts->cancelled
        );
    }
}
