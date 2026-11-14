<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Service;

use DateTimeInterface;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsGranularity;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRankItem;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRankMeasure;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRanking;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsShareSeries;
use Dantweb\PeroxidAnalytics\Analytics\Exception\AnalyticsRankingException;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\RankedFigure;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\RankingRepositoryInterface;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\RankingSubject;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\ShopCurrencyInterface;

/**
 * Turns the repository's top rows into a ranking: the bounds of the list, the "others" remainder, and shares with one
 * decimal that add up to 100. The sorting and the sums are the database's work.
 */
final class RankingReader implements RankingReaderInterface
{
    public const MIN_LIMIT = 1;

    public const MAX_LIMIT = 20;

    private const NO_CATEGORY = 'No category';

    private const MONEY_DECIMALS = 2;

    public function __construct(
        private readonly RankingRepositoryInterface $repository,
        private readonly ShopCurrencyInterface $currency,
        private readonly ShareSeriesBuilder $seriesBuilder,
        private readonly ShareRounder $rounder
    ) {
    }

    public function topProducts(
        DateTimeInterface $from,
        DateTimeInterface $to,
        int $limit,
        AdminAnalyticsRankMeasure $measure
    ): AdminAnalyticsRanking {
        return $this->ranking(RankingSubject::PRODUCTS, $from, $to, $limit, $measure);
    }

    public function topCategories(
        DateTimeInterface $from,
        DateTimeInterface $to,
        int $limit,
        AdminAnalyticsRankMeasure $measure
    ): AdminAnalyticsRanking {
        return $this->ranking(RankingSubject::CATEGORIES, $from, $to, $limit, $measure);
    }

    public function topShareSeries(
        DateTimeInterface $from,
        DateTimeInterface $to,
        AdminAnalyticsGranularity $granularity,
        int $limit,
        AdminAnalyticsRankMeasure $measure
    ): AdminAnalyticsShareSeries {
        $this->assertLimit($limit);
        $range = AnalyticsRange::of($from, $to);
        $productIds = $this->topProductIds($range, $measure, $limit);
        $days = $productIds === [] ? [] : $this->repository->shareByDay($range, $measure, $productIds);

        return $this->seriesBuilder->build($range, $granularity, $measure, $days);
    }

    private function ranking(
        RankingSubject $subject,
        DateTimeInterface $from,
        DateTimeInterface $to,
        int $limit,
        AdminAnalyticsRankMeasure $measure
    ): AdminAnalyticsRanking {
        $this->assertLimit($limit);
        $range = AnalyticsRange::of($from, $to);
        $figures = $this->repository->top($subject, $range, $measure, $limit);
        $total = round($this->repository->total($subject, $range, $measure), self::MONEY_DECIMALS);
        $values = array_map(static fn (RankedFigure $figure): float => $figure->value, $figures);
        $topTotal = round(array_sum($values), self::MONEY_DECIMALS);
        $others = max(0.0, round($total - $topTotal, self::MONEY_DECIMALS));
        $shares = $this->rounder->percentages([...$values, $others], $total);
        $othersShare = (float) array_pop($shares);

        return new AdminAnalyticsRanking(
            $range->from(),
            $range->to(),
            $measure,
            $this->currency->code(),
            $total,
            $topTotal,
            round(array_sum($shares), 1),
            $others,
            $othersShare,
            $this->items($figures, $shares)
        );
    }

    /**
     * @param list<RankedFigure> $figures
     * @param list<float> $shares
     * @return list<AdminAnalyticsRankItem>
     */
    private function items(array $figures, array $shares): array
    {
        $items = [];
        foreach ($figures as $index => $figure) {
            $items[] = new AdminAnalyticsRankItem(
                $index + 1,
                $figure->entityId,
                $figure->number,
                $figure->entityId === null ? self::NO_CATEGORY : $figure->title,
                round($figure->value, self::MONEY_DECIMALS),
                $shares[$index]
            );
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private function topProductIds(AnalyticsRange $range, AdminAnalyticsRankMeasure $measure, int $limit): array
    {
        $ids = [];
        foreach ($this->repository->top(RankingSubject::PRODUCTS, $range, $measure, $limit) as $figure) {
            if ($figure->entityId !== null) {
                $ids[] = $figure->entityId;
            }
        }

        return $ids;
    }

    private function assertLimit(int $limit): void
    {
        if ($limit < self::MIN_LIMIT || $limit > self::MAX_LIMIT) {
            throw AnalyticsRankingException::limitOutOfBounds(self::MIN_LIMIT, self::MAX_LIMIT);
        }
    }
}
