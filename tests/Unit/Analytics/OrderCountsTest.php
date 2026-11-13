<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Unit\Analytics;

use DateTimeImmutable;
use Dantweb\PeroxidAnalytics\Analytics\Exception\AnalyticsRangeException;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\OrderCountFigures;
use Dantweb\PeroxidAnalytics\Analytics\Service\AnalyticsReader;
use Dantweb\PeroxidAnalytics\Analytics\Service\BucketCalendar;
use Dantweb\PeroxidAnalytics\Tests\Support\FakeAnalyticsRepository;
use Dantweb\PeroxidAnalytics\Tests\Support\FixedCurrency;
use PHPUnit\Framework\TestCase;

/** Order counts of a period: total, paid, shipped, cancelled — the analytics module owns every order number. */
final class OrderCountsTest extends TestCase
{
    public function testOrderCountsSumsFromTheRepositoryForTheRange(): void
    {
        $repository = new FakeAnalyticsRepository([], new OrderCountFigures(5, 3, 2, 1));
        $reader = new AnalyticsReader($repository, new FixedCurrency('EUR'), new BucketCalendar());

        $counts = $reader->orderCounts(new DateTimeImmutable('2026-11-12 15:00'), new DateTimeImmutable('2026-11-12'));

        $this->assertSame([5, 3, 2, 1], [$counts->total(), $counts->paid(), $counts->shipped(), $counts->cancelled()]);
        $this->assertSame(
            ['2026-11-12', '2026-11-12'],
            [$counts->from()->format('Y-m-d'), $counts->to()->format('Y-m-d')]
        );
        $this->assertSame('2026-11-12', $repository->askedFor?->from()->format('Y-m-d'));
    }

    public function testOrderCountsToBeforeFromRefused(): void
    {
        $this->expectException(AnalyticsRangeException::class);

        $this->reader()->orderCounts(new DateTimeImmutable('2026-11-12'), new DateTimeImmutable('2026-11-11'));
    }

    public function testOrderCountsRangeOver366DaysRefused(): void
    {
        $this->expectException(AnalyticsRangeException::class);

        $this->reader()->orderCounts(new DateTimeImmutable('2025-01-01'), new DateTimeImmutable('2026-01-03'));
    }

    private function reader(): AnalyticsReader
    {
        return new AnalyticsReader(new FakeAnalyticsRepository([]), new FixedCurrency('EUR'), new BucketCalendar());
    }
}
