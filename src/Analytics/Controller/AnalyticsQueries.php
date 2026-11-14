<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Controller;

use DateTimeInterface;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsGranularity;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsOrderCounts;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRankMeasure;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRanking;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsSeries;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsShareSeries;
use Dantweb\PeroxidAnalytics\Analytics\Service\AnalyticsReaderInterface;
use Dantweb\PeroxidAnalytics\Analytics\Service\RankingReaderInterface;
use Dantweb\PeroxidAnalytics\Authorization\AnalyticsRightsProvider;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Right;

/**
 * The admin analytics query. `#[Right]` without `#[Logged]`, like every operation of the admin API, so that a
 * refusal carries an `errorCode` (`admin_session_required`, `permission_denied`). Mapping only; the rules are in
 * the reader.
 */
final class AnalyticsQueries
{
    public function __construct(
        private readonly AnalyticsReaderInterface $reader,
        private readonly RankingReaderInterface $rankings
    ) {
    }

    /**
     * Orders, gross sales and new customers of the shop per day or ISO week between `from` and `to` (whole days,
     * both included; at most 366 days), with the totals of the period. Aggregates only: no order, no customer.
     */
    #[Query]
    #[Right(AnalyticsRightsProvider::VIEW)]
    public function adminAnalyticsSeries(
        DateTimeInterface $from,
        DateTimeInterface $to,
        AdminAnalyticsGranularity $granularity
    ): AdminAnalyticsSeries {
        return $this->reader->series($from, $to, $granularity);
    }

    /**
     * How many orders the shop got between `from` and `to` (whole days, both included; at most 366 days): total
     * (cancelled ones included), paid, shipped, cancelled. Counts only: no order, no customer.
     */
    #[Query]
    #[Right(AnalyticsRightsProvider::VIEW)]
    public function adminAnalyticsOrderCounts(DateTimeInterface $from, DateTimeInterface $to): AdminAnalyticsOrderCounts
    {
        return $this->reader->orderCounts($from, $to);
    }

    /**
     * The best selling products between `from` and `to` (whole days, at most 366 days), at most `limit` (1 to 20) with
     * their share of the total and an "others" remainder. A variant counts for its parent, cancelled orders and items
     * are left out. Aggregates only: no order, no customer.
     */
    #[Query]
    #[Right(AnalyticsRightsProvider::VIEW)]
    public function adminAnalyticsTopProducts(
        DateTimeInterface $from,
        DateTimeInterface $to,
        int $limit,
        AdminAnalyticsRankMeasure $measure
    ): AdminAnalyticsRanking {
        return $this->rankings->topProducts($from, $to, $limit, $measure);
    }

    /**
     * The best selling categories of the period; a sale counts for the main category of its product only. Same
     * arguments, rules and answer as the products.
     */
    #[Query]
    #[Right(AnalyticsRightsProvider::VIEW)]
    public function adminAnalyticsTopCategories(
        DateTimeInterface $from,
        DateTimeInterface $to,
        int $limit,
        AdminAnalyticsRankMeasure $measure
    ): AdminAnalyticsRanking {
        return $this->rankings->topCategories($from, $to, $limit, $measure);
    }

    /**
     * The share that the period's top `limit` products hold in every bucket of the complete calendar (an empty bucket
     * is 0), and over the whole period.
     */
    #[Query]
    #[Right(AnalyticsRightsProvider::VIEW)]
    public function adminAnalyticsTopShareSeries(
        DateTimeInterface $from,
        DateTimeInterface $to,
        AdminAnalyticsGranularity $granularity,
        int $limit,
        AdminAnalyticsRankMeasure $measure
    ): AdminAnalyticsShareSeries {
        return $this->rankings->topShareSeries($from, $to, $granularity, $limit, $measure);
    }
}
