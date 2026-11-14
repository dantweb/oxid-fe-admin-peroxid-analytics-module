<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Integration;

use DateTimeImmutable;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsGranularity;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRankMeasure;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsShareSeries;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsShareBucket;
use Dantweb\PeroxidAnalytics\Tests\Support\RankingFixtureTestCase;

/**
 * The share of the period's top products per bucket over the planted orders: one bucket per day of the period, an
 * empty one is 0 (not missing).
 */
final class TopShareSeriesReaderTest extends RankingFixtureTestCase
{
    public function testBySalesTheTopProductsShareOfEveryDayWithAnEmptyDayAsZero(): void
    {
        $series = $this->series(1, AdminAnalyticsRankMeasure::SALES, AdminAnalyticsGranularity::DAILY);

        $this->assertSame(
            [['2031-06-01', 20.0, 50.0, 40.0], ['2031-06-02', 10.0, 16.0, 62.5], ['2031-06-03', 0.0, 0.0, 0.0]],
            array_map(self::row(...), $series->buckets())
        );
        $this->assertSame(['total', 30.0, 66.0, 45.5], self::row($series->totals()));
    }

    public function testByOrdersTheOrdersWithAProductOfTheTopOverAllOrderProductPairs(): void
    {
        $series = $this->series(1, AdminAnalyticsRankMeasure::ORDERS, AdminAnalyticsGranularity::DAILY);

        $this->assertSame(
            [['2031-06-01', 1.0, 2.0, 50.0], ['2031-06-02', 1.0, 3.0, 33.3], ['2031-06-03', 0.0, 0.0, 0.0]],
            array_map(self::row(...), $series->buckets())
        );
    }

    public function testWeeklyFoldsTheDaysIntoTheMonday(): void
    {
        $series = $this->series(1, AdminAnalyticsRankMeasure::SALES, AdminAnalyticsGranularity::WEEKLY);

        // 2031-06-01 is a Sunday: its own week starts on 2031-05-26, the next week on 2031-06-02
        $this->assertSame(
            [['2031-05-26', 20.0, 50.0, 40.0], ['2031-06-02', 10.0, 16.0, 62.5]],
            array_map(self::row(...), $series->buckets())
        );
    }

    private function series(
        int $limit,
        AdminAnalyticsRankMeasure $measure,
        AdminAnalyticsGranularity $granularity
    ): AdminAnalyticsShareSeries {
        return $this->reader()->topShareSeries(
            new DateTimeImmutable(self::FROM),
            new DateTimeImmutable(self::TO),
            $granularity,
            $limit,
            $measure
        );
    }

    /**
     * @return array{string, float, float, float}
     */
    private static function row(AdminAnalyticsShareBucket $bucket): array
    {
        return [$bucket->key(), $bucket->value(), $bucket->total(), $bucket->share()];
    }
}
