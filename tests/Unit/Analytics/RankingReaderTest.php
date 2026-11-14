<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Unit\Analytics;

use DateTimeImmutable;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsGranularity;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRankItem;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRankMeasure;
use Dantweb\PeroxidAnalytics\Analytics\Exception\AnalyticsRankingException;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\RankedFigure;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\RankingSubject;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\ShareDayFigures;
use Dantweb\PeroxidAnalytics\Analytics\Service\BucketCalendar;
use Dantweb\PeroxidAnalytics\Analytics\Service\RankingBuilder;
use Dantweb\PeroxidAnalytics\Analytics\Service\RankingReader;
use Dantweb\PeroxidAnalytics\Analytics\Service\ShareSeriesBuilder;
use Dantweb\PeroxidAnalytics\Analytics\Service\ShareRounder;
use Dantweb\PeroxidAnalytics\Tests\Support\FakeRankingRepository;
use Dantweb\PeroxidAnalytics\Tests\Support\FixedCurrency;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RankingReaderTest extends TestCase
{
    private const FROM = '2026-11-02';
    private const TO = '2026-11-04';

    public function testTheTopProductsWithOthersAndTheShareOfTheTop(): void
    {
        $reader = $this->reader([
            new RankedFigure('p1', 'A-1', 'Kuyichi Jeans', 50.0),
            new RankedFigure('p2', 'A-2', 'Bar Set', 30.0),
        ], 100.0);

        $ranking = $reader->topProducts(
            $this->day(self::FROM),
            $this->day(self::TO),
            2,
            AdminAnalyticsRankMeasure::SALES
        );

        $this->assertSame('SALES', $ranking->measure()->value);
        $this->assertSame('CHF', $ranking->currency());
        $this->assertSame([100.0, 80.0, 20.0], [$ranking->total(), $ranking->topTotal(), $ranking->others()]);
        $this->assertSame([80.0, 20.0], [$ranking->topShare(), $ranking->othersShare()]);
        $this->assertSame(
            [[1, 'p1', 'A-1', 'Kuyichi Jeans', 50.0, 50.0], [2, 'p2', 'A-2', 'Bar Set', 30.0, 30.0]],
            array_map(self::row(...), $ranking->items())
        );
    }

    public function testTheSharesOfTheItemsAndOthersAddUpToOneHundred(): void
    {
        $reader = $this->reader(
            [new RankedFigure('p1', null, 'One', 1.0), new RankedFigure('p2', null, 'Two', 1.0)],
            3.0
        );

        $ranking = $reader->topProducts(
            $this->day(self::FROM),
            $this->day(self::TO),
            2,
            AdminAnalyticsRankMeasure::UNITS
        );

        $sum = $ranking->othersShare();
        foreach ($ranking->items() as $item) {
            $sum += $item->share();
        }
        $this->assertEqualsWithDelta(100.0, $sum, 0.0001);
        $this->assertEqualsWithDelta(100.0, $ranking->topShare() + $ranking->othersShare(), 0.0001);
    }

    public function testAPeriodWithoutSalesIsAnEmptyRanking(): void
    {
        $ranking = $this->reader([], 0.0)->topProducts(
            $this->day(self::FROM),
            $this->day(self::TO),
            5,
            AdminAnalyticsRankMeasure::ORDERS
        );

        $this->assertSame([], $ranking->items());
        $this->assertSame([0.0, 0.0, 0.0, 0.0], [
            $ranking->total(), $ranking->topTotal(), $ranking->topShare(), $ranking->othersShare(),
        ]);
    }

    public function testTheRepositoryIsAskedForTheRangeTheLimitAndTheMeasure(): void
    {
        $repository = new FakeRankingRepository([], 0.0);
        $reader = new RankingReader($repository, new FixedCurrency(), self::builder(), self::seriesBuilder());

        $reader->topCategories(
            $this->day(self::FROM),
            $this->day(self::TO),
            7,
            AdminAnalyticsRankMeasure::ORDERS
        );

        $this->assertSame(RankingSubject::CATEGORIES, $repository->subjectAskedFor);
        $this->assertSame(7, $repository->limitAskedFor);
        $this->assertSame(AdminAnalyticsRankMeasure::ORDERS, $repository->measureAskedFor);
        $this->assertSame('2026-11-05 00:00:00', $repository->askedFor?->until()->format('Y-m-d H:i:s'));
    }

    public function testAnItemWithoutACategoryIsNamedNoCategoryAndHasNoId(): void
    {
        $reader = $this->reader([new RankedFigure(null, null, '', 10.0)], 10.0);

        $ranking = $reader->topCategories(
            $this->day(self::FROM),
            $this->day(self::TO),
            5,
            AdminAnalyticsRankMeasure::SALES
        );

        $this->assertSame([[1, null, null, 'No category', 10.0, 100.0]], array_map(self::row(...), $ranking->items()));
    }

    #[DataProvider('invalidLimits')]
    public function testALimitOutsideOneToTwentyIsRefused(int $limit): void
    {
        $this->expectException(AnalyticsRankingException::class);
        $this->expectExceptionMessage('between 1 and 20');

        $this->reader([], 0.0)->topProducts(
            $this->day(self::FROM),
            $this->day(self::TO),
            $limit,
            AdminAnalyticsRankMeasure::SALES
        );
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalidLimits(): array
    {
        return ['zero' => [0], 'negative' => [-3], 'twenty-one' => [21]];
    }

    public function testTheBoundsOneAndTwentyAreAccepted(): void
    {
        $reader = $this->reader([], 0.0);

        $one = $reader->topProducts(
            $this->day(self::FROM),
            $this->day(self::TO),
            1,
            AdminAnalyticsRankMeasure::SALES
        );
        $twenty = $reader->topProducts(
            $this->day(self::FROM),
            $this->day(self::TO),
            20,
            AdminAnalyticsRankMeasure::SALES
        );

        $this->assertSame([], $one->items());
        $this->assertSame([], $twenty->items());
    }

    public function testAPeriodLongerThanAYearIsRefusedWithTheRangeError(): void
    {
        $this->expectExceptionMessage('longer than 366 days');

        $this->reader([], 0.0)->topProducts(
            $this->day('2025-01-01'),
            $this->day('2026-11-04'),
            5,
            AdminAnalyticsRankMeasure::SALES
        );
    }

    public function testTheShareSeriesHasEveryDayEmptyOnesAsZero(): void
    {
        $repository = new FakeRankingRepository(
            [new RankedFigure('p1', null, 'One', 9.0)],
            10.0,
            [new ShareDayFigures('2026-11-02', 30.0, 40.0), new ShareDayFigures('2026-11-04', 0.0, 5.0)]
        );
        $reader = new RankingReader($repository, new FixedCurrency(), self::builder(), self::seriesBuilder());

        $series = $reader->topShareSeries(
            $this->day(self::FROM),
            $this->day(self::TO),
            AdminAnalyticsGranularity::DAILY,
            3,
            AdminAnalyticsRankMeasure::SALES
        );

        $this->assertSame(['p1'], $repository->productIdsAskedFor);
        $this->assertSame(
            [['2026-11-02', 30.0, 40.0, 75.0], ['2026-11-03', 0.0, 0.0, 0.0], ['2026-11-04', 0.0, 5.0, 0.0]],
            array_map(
                static fn ($bucket): array => [$bucket->key(), $bucket->value(), $bucket->total(), $bucket->share()],
                $series->buckets()
            )
        );
        $totals = $series->totals();
        $this->assertSame(
            ['total', 30.0, 45.0, 66.7],
            [$totals->key(), $totals->value(), $totals->total(), $totals->share()]
        );
    }

    public function testTheWeeklyShareSeriesFoldsDaysIntoTheMondayAndSharesTheSums(): void
    {
        $repository = new FakeRankingRepository(
            [new RankedFigure('p1', null, 'One', 9.0)],
            10.0,
            [new ShareDayFigures('2026-11-02', 10.0, 40.0), new ShareDayFigures('2026-11-04', 30.0, 40.0)]
        );
        $reader = new RankingReader($repository, new FixedCurrency(), self::builder(), self::seriesBuilder());

        $series = $reader->topShareSeries(
            $this->day(self::FROM),
            $this->day(self::TO),
            AdminAnalyticsGranularity::WEEKLY,
            3,
            AdminAnalyticsRankMeasure::SALES
        );

        $this->assertCount(1, $series->buckets());
        $this->assertSame(['2026-11-02', 40.0, 80.0, 50.0], [
            $series->buckets()[0]->key(),
            $series->buckets()[0]->value(),
            $series->buckets()[0]->total(),
            $series->buckets()[0]->share(),
        ]);
    }

    public function testNoTopProductsMeansAShareSeriesOfZerosWithoutAskingForDays(): void
    {
        $repository = new FakeRankingRepository([], 0.0);
        $reader = new RankingReader($repository, new FixedCurrency(), self::builder(), self::seriesBuilder());

        $series = $reader->topShareSeries(
            $this->day(self::FROM),
            $this->day(self::TO),
            AdminAnalyticsGranularity::DAILY,
            3,
            AdminAnalyticsRankMeasure::SALES
        );

        $this->assertCount(3, $series->buckets());
        $this->assertSame(0.0, $series->totals()->share());
    }

    /**
     * @param list<RankedFigure> $figures
     */
    private function reader(array $figures, float $total): RankingReader
    {
        return new RankingReader(
            new FakeRankingRepository($figures, $total),
            new FixedCurrency('CHF'),
            self::builder(),
            self::seriesBuilder()
        );
    }

    private static function builder(): RankingBuilder
    {
        return new RankingBuilder(new ShareRounder());
    }

    private static function seriesBuilder(): ShareSeriesBuilder
    {
        return new ShareSeriesBuilder(new BucketCalendar(), new ShareRounder());
    }

    private function day(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date);
    }

    /**
     * @return array{int, ?string, ?string, string, float, float}
     */
    private static function row(AdminAnalyticsRankItem $item): array
    {
        return [$item->rank(), $item->entityId(), $item->number(), $item->title(), $item->value(), $item->share()];
    }
}
