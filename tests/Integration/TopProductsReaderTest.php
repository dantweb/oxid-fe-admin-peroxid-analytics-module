<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Integration;

use DateTimeImmutable;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRankItem;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRankMeasure;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRanking;
use Dantweb\PeroxidAnalytics\Tests\Support\RankingFixtureTestCase;

/**
 * The ranking of products over planted orders (see RankingFixtureTestCase): cancelled orders and items left out,
 * variants summed into the parent, another shop's orders and other days not counted.
 */
final class TopProductsReaderTest extends RankingFixtureTestCase
{
    public function testBySalesVariantsAreSummedIntoTheParentAndCancelledOnesAreLeftOut(): void
    {
        $ranking = $this->top(10, AdminAnalyticsRankMeasure::SALES);

        $this->assertSame(
            [
                [1, 'pxr-alpha', 'ALPHA-0', 'Alpha', 30.0, 45.5],
                [2, 'pxr-beta', 'BETA-0', 'Beta', 30.0, 45.4],
                [3, 'pxr-gamma', 'GAMMA-0', 'Gamma', 5.0, 7.6],
                [4, 'pxr-gone', 'GONE-1', 'Gone', 1.0, 1.5],
            ],
            array_map(self::row(...), $ranking->items())
        );
        $this->assertSame([66.0, 66.0, 0.0], [$ranking->total(), $ranking->topTotal(), $ranking->others()]);
        $this->assertSame([100.0, 0.0], [$ranking->topShare(), $ranking->othersShare()]);
    }

    public function testByUnitsTheAmountsOfTheItemsAreSummed(): void
    {
        $ranking = $this->top(10, AdminAnalyticsRankMeasure::UNITS);

        $this->assertSame(
            [['pxr-gamma', 5.0], ['pxr-alpha', 3.0], ['pxr-beta', 1.0], ['pxr-gone', 1.0]],
            $this->pairs($ranking)
        );
        $this->assertSame(10.0, $ranking->total());
    }

    public function testByOrdersEveryOrderCountsOncePerProduct(): void
    {
        $ranking = $this->top(10, AdminAnalyticsRankMeasure::ORDERS);

        $this->assertSame(
            [['pxr-alpha', 2.0], ['pxr-beta', 1.0], ['pxr-gamma', 1.0], ['pxr-gone', 1.0]],
            $this->pairs($ranking)
        );
        $this->assertSame(5.0, $ranking->total());
    }

    public function testTheLimitCutsTheListAndOthersHoldsTheRest(): void
    {
        $ranking = $this->top(2, AdminAnalyticsRankMeasure::SALES);

        $this->assertSame(['pxr-alpha', 'pxr-beta'], array_map(
            static fn (AdminAnalyticsRankItem $item): ?string => $item->entityId(),
            $ranking->items()
        ));
        $this->assertSame([66.0, 60.0, 6.0], [$ranking->total(), $ranking->topTotal(), $ranking->others()]);
        $this->assertSame([90.9, 9.1], [$ranking->topShare(), $ranking->othersShare()]);
    }

    public function testTheTitleIsReadInTheLanguageOfTheRequestWithTheStoredTitleAsFallback(): void
    {
        $ranking = $this->top(10, AdminAnalyticsRankMeasure::SALES);

        $this->assertSame('Gone', $ranking->items()[3]->title(), 'a deleted product keeps the title of the order item');
        $this->assertSame('Alpha', $ranking->items()[0]->title());
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
        return $this->reader()->topProducts(
            new DateTimeImmutable(self::FROM),
            new DateTimeImmutable(self::TO),
            $limit,
            $measure
        );
    }

    /**
     * @return array{int, ?string, ?string, string, float, float}
     */
    private static function row(AdminAnalyticsRankItem $item): array
    {
        return [$item->rank(), $item->entityId(), $item->number(), $item->title(), $item->value(), $item->share()];
    }
}
