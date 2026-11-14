<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Integration;

use DateTimeImmutable;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRankItem;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRankMeasure;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRanking;
use Dantweb\PeroxidAnalytics\Tests\Support\RankingFixtureTestCase;

/**
 * The ranking of categories over the same planted orders: a sale counts for the main category of its product only
 * (the assignment with the lowest OXTIME; a variant counts through its parent), the rest is "No category".
 */
final class TopCategoriesReaderTest extends RankingFixtureTestCase
{
    public function testASaleCountsForTheMainCategoryOnlyAndTheRestIsNoCategory(): void
    {
        $ranking = $this->top(10, AdminAnalyticsRankMeasure::SALES);

        $this->assertSame(
            [
                [1, 'pxr-shirts', 'Shirts', 30.0, 45.5],
                [2, 'pxr-shoes', 'Shoes', 30.0, 45.4],
                [3, null, 'No category', 6.0, 9.1],
            ],
            array_map(self::row(...), $ranking->items())
        );
        $this->assertSame(66.0, $ranking->total());
    }

    public function testTheSharesOfTheItemsAndOthersAddUpToOneHundred(): void
    {
        $ranking = $this->top(1, AdminAnalyticsRankMeasure::SALES);

        $sum = $ranking->othersShare();
        foreach ($ranking->items() as $item) {
            $sum += $item->share();
        }
        $this->assertEqualsWithDelta(100.0, $sum, 0.0001);
        $this->assertSame([30.0, 36.0], [$ranking->topTotal(), $ranking->others()]);
    }

    public function testByUnitsAndByOrders(): void
    {
        $units = $this->top(10, AdminAnalyticsRankMeasure::UNITS);
        $orders = $this->top(10, AdminAnalyticsRankMeasure::ORDERS);

        $this->assertSame([[null, 6.0], ['pxr-shirts', 3.0], ['pxr-shoes', 1.0]], $this->pairs($units));
        $this->assertSame([['pxr-shirts', 2.0], ['pxr-shoes', 1.0], [null, 1.0]], $this->pairs($orders));
    }

    /**
     * @return list<array{?string, float}>
     */
    private function pairs(AdminAnalyticsRanking $ranking): array
    {
        return array_map(
            static fn (AdminAnalyticsRankItem $item): array => [$item->entityId(), $item->value()],
            $ranking->items()
        );
    }

    private function top(int $limit, AdminAnalyticsRankMeasure $measure): AdminAnalyticsRanking
    {
        return $this->reader()->topCategories(
            new DateTimeImmutable(self::FROM),
            new DateTimeImmutable(self::TO),
            $limit,
            $measure
        );
    }

    /**
     * @return array{int, ?string, string, float, float}
     */
    private static function row(AdminAnalyticsRankItem $item): array
    {
        return [$item->rank(), $item->entityId(), $item->title(), $item->value(), $item->share()];
    }
}
