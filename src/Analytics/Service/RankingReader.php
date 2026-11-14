<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Service;

use DateTimeInterface;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsGranularity;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRankMeasure;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRanking;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsShareSeries;
use Dantweb\PeroxidAnalytics\Analytics\Exception\AnalyticsRankingException;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\RankingRepositoryInterface;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\RankingSubject;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\ShopCurrencyInterface;

/**
 * Validates the period and the bounds of the list and asks the repository; the sorting and the sums are the
 * database's work, the ranking and the share curve are put together by the builders.
 */
final class RankingReader implements RankingReaderInterface
{
    public const MIN_LIMIT = 1;

    public const MAX_LIMIT = 20;

    public function __construct(
        private readonly RankingRepositoryInterface $repository,
        private readonly ShopCurrencyInterface $currency,
        private readonly RankingBuilder $builder,
        private readonly ShareSeriesBuilder $seriesBuilder
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

        return $this->builder->build(
            $measure,
            $this->currency->code(),
            $this->repository->top($subject, $range, $measure, $limit),
            $this->repository->total($subject, $range, $measure)
        );
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
