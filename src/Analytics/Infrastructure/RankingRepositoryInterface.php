<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Infrastructure;

use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRankMeasure;
use Dantweb\PeroxidAnalytics\Analytics\Service\AnalyticsRange;

/**
 * Rankings of the order items of the shop of the request (cancelled orders and cancelled items left out), aggregated
 * in the database: only the top rows and sums leave it.
 */
interface RankingRepositoryInterface
{
    /**
     * @return list<RankedFigure> best first (ties by title, then id), at most `limit`
     */
    public function top(
        RankingSubject $subject,
        AnalyticsRange $range,
        AdminAnalyticsRankMeasure $measure,
        int $limit
    ): array;

    /** The measure summed over every product (or category) of the period, the whole the shares refer to. */
    public function total(RankingSubject $subject, AnalyticsRange $range, AdminAnalyticsRankMeasure $measure): float;

    /**
     * Per calendar day with sales: the measure of the given products and of all products.
     *
     * @param list<string> $productIds
     * @return list<ShareDayFigures>
     */
    public function shareByDay(AnalyticsRange $range, AdminAnalyticsRankMeasure $measure, array $productIds): array;
}
