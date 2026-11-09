<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Unit\Analytics;

use DateTimeImmutable;
use Dantweb\PeroxidAnalytics\Analytics\Exception\AnalyticsRangeException;
use Dantweb\PeroxidAnalytics\Analytics\Service\AnalyticsRange;
use PHPUnit\Framework\TestCase;

final class AnalyticsRangeTest extends TestCase
{
    public function testNormalisesToWholeDaysAndCountsBothEnds(): void
    {
        $range = AnalyticsRange::of(
            new DateTimeImmutable('2026-11-01 13:45'),
            new DateTimeImmutable('2026-11-04 09:10')
        );

        $this->assertSame('2026-11-01 00:00:00', $range->from()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-04 00:00:00', $range->to()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-05 00:00:00', $range->until()->format('Y-m-d H:i:s'));
        $this->assertSame(4, $range->days());
    }

    public function testRefusesAPeriodThatEndsBeforeItStartsWithTheErrorCode(): void
    {
        $this->expectException(AnalyticsRangeException::class);
        try {
            AnalyticsRange::of(new DateTimeImmutable('2026-11-05'), new DateTimeImmutable('2026-11-04'));
        } catch (AnalyticsRangeException $exception) {
            $this->assertSame(['errorCode' => 'analytics_range_invalid'], $exception->getExtensions());
            $this->assertSame(400, $exception->getCode());
            throw $exception;
        }
    }

    public function testAllowsAYearAndRefusesMore(): void
    {
        $year = AnalyticsRange::of(new DateTimeImmutable('2025-11-05'), new DateTimeImmutable('2026-11-05'));
        $this->assertSame(366, $year->days());

        $this->expectException(AnalyticsRangeException::class);
        $this->expectExceptionMessage('longer than 366 days');
        AnalyticsRange::of(new DateTimeImmutable('2025-11-04'), new DateTimeImmutable('2026-11-05'));
    }
}
