<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Unit\Analytics;

use DateTimeImmutable;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsGranularity;
use Dantweb\PeroxidAnalytics\Analytics\Service\AnalyticsRange;
use Dantweb\PeroxidAnalytics\Analytics\Service\BucketCalendar;
use PHPUnit\Framework\TestCase;

final class BucketCalendarTest extends TestCase
{
    private BucketCalendar $calendar;

    protected function setUp(): void
    {
        $this->calendar = new BucketCalendar();
    }

    public function testADayKeyIsTheCalendarDayAndAWeekKeyTheMondayOfItsIsoWeek(): void
    {
        $wednesdayNight = new DateTimeImmutable('2026-11-04 23:59:59'); // a Wednesday
        $sunday = new DateTimeImmutable('2026-11-01 10:00:00');

        $this->assertSame('2026-11-04', $this->calendar->keyOf($wednesdayNight, AdminAnalyticsGranularity::DAILY));
        $this->assertSame('2026-11-02', $this->calendar->keyOf($wednesdayNight, AdminAnalyticsGranularity::WEEKLY));
        $this->assertSame('2026-10-26', $this->calendar->keyOf($sunday, AdminAnalyticsGranularity::WEEKLY));
        $monday = new DateTimeImmutable('2026-11-02');
        $this->assertSame('2026-11-02', $this->calendar->keyOf($monday, AdminAnalyticsGranularity::WEEKLY));
    }

    public function testTheKeysOfAPeriodAreCompleteAndOrderedEmptyDaysIncluded(): void
    {
        $range = AnalyticsRange::of(new DateTimeImmutable('2026-10-29'), new DateTimeImmutable('2026-11-04'));

        $this->assertSame(
            ['2026-10-29', '2026-10-30', '2026-10-31', '2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04'],
            $this->calendar->keysOf($range, AdminAnalyticsGranularity::DAILY)
        );
        $this->assertSame(
            ['2026-10-26', '2026-11-02'],
            $this->calendar->keysOf($range, AdminAnalyticsGranularity::WEEKLY)
        );
    }

    public function testASingleDayPeriodHasOneBucket(): void
    {
        $range = AnalyticsRange::of(
            new DateTimeImmutable('2026-11-04 08:00'),
            new DateTimeImmutable('2026-11-04 20:00')
        );

        $this->assertSame(['2026-11-04'], $this->calendar->keysOf($range, AdminAnalyticsGranularity::DAILY));
        $this->assertSame(['2026-11-02'], $this->calendar->keysOf($range, AdminAnalyticsGranularity::WEEKLY));
    }
}
