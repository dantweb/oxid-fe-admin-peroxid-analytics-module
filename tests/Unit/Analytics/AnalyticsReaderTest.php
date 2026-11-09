<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Unit\Analytics;

use DateTimeImmutable;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsBucket;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsGranularity;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\DailyFigures;
use Dantweb\PeroxidAnalytics\Analytics\Service\AnalyticsReader;
use Dantweb\PeroxidAnalytics\Analytics\Service\BucketCalendar;
use Dantweb\PeroxidAnalytics\Tests\Support\FakeAnalyticsRepository;
use Dantweb\PeroxidAnalytics\Tests\Support\FixedCurrency;
use PHPUnit\Framework\TestCase;

final class AnalyticsReaderTest extends TestCase
{
    private FakeAnalyticsRepository $repository;

    private AnalyticsReader $reader;

    protected function setUp(): void
    {
        $this->repository = new FakeAnalyticsRepository([
            new DailyFigures('2026-11-02', 2, 40.0, 1),
            new DailyFigures('2026-11-04', 1, 5.5, 2),
            new DailyFigures('2026-10-01', 9, 999.0, 9), // outside the period: never in the answer
        ]);
        $this->reader = new AnalyticsReader($this->repository, new FixedCurrency('CHF'), new BucketCalendar());
    }

    public function testDailyEveryDayOfThePeriodIsABucketEmptyOnesAsZerosRatiosZeroSafe(): void
    {
        $series = $this->reader->series(
            new DateTimeImmutable('2026-11-02'),
            new DateTimeImmutable('2026-11-04'),
            AdminAnalyticsGranularity::DAILY
        );

        $this->assertSame('CHF', $series->currency());
        $this->assertSame(AdminAnalyticsGranularity::DAILY, $series->granularity());
        $this->assertSame('2026-11-02', $series->from()->format('Y-m-d'));
        $this->assertSame('2026-11-04', $series->to()->format('Y-m-d'));
        $this->assertSame(
            [
                ['2026-11-02', 2, 40.0, 1, 2.0, 40.0],
                ['2026-11-03', 0, 0.0, 0, 0.0, 0.0],
                ['2026-11-04', 1, 5.5, 2, 0.5, 2.75],
            ],
            array_map(self::row(...), $series->buckets())
        );
        $this->assertSame(['total', 3, 45.5, 3, 1.0, 15.17], self::row($series->totals()));
        $this->assertSame('2026-11-05 00:00:00', $this->repository->askedFor?->until()->format('Y-m-d H:i:s'));
    }

    public function testWeeklyFoldsTheDaysOfAnIsoWeekIntoItsMonday(): void
    {
        $series = $this->reader->series(
            new DateTimeImmutable('2026-11-02'),
            new DateTimeImmutable('2026-11-04'),
            AdminAnalyticsGranularity::WEEKLY
        );

        $this->assertSame([['2026-11-02', 3, 45.5, 3, 1.0, 15.17]], array_map(self::row(...), $series->buckets()));
        $this->assertSame(3, $series->totals()->orders());
    }

    public function testAPeriodWithoutDataIsAllZeros(): void
    {
        $reader = new AnalyticsReader(new FakeAnalyticsRepository([]), new FixedCurrency(), new BucketCalendar());

        $series = $reader->series(
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-01-02'),
            AdminAnalyticsGranularity::DAILY
        );

        $this->assertCount(2, $series->buckets());
        $this->assertSame(0, $series->totals()->orders());
        $this->assertSame(0.0, $series->totals()->salesPerCustomer());
        $this->assertSame('EUR', $series->currency());
    }

    /**
     * @return array{string, int, float, int, float, float}
     */
    private static function row(AdminAnalyticsBucket $bucket): array
    {
        return [
            $bucket->key(),
            $bucket->orders(),
            $bucket->sales(),
            $bucket->customers(),
            $bucket->ordersPerCustomer(),
            $bucket->salesPerCustomer(),
        ];
    }
}
