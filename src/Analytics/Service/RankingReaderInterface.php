<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Service;

use DateTimeInterface;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsGranularity;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRankMeasure;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRanking;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsShareSeries;

interface RankingReaderInterface
{
    /**
     * The best selling products between `from` and `to` (whole days, both included; at most 366 days): a variant counts
     * for its parent product, cancelled orders and items are left out. `limit` is 1 to 20.
     *
     * @throws \Dantweb\PeroxidAnalytics\Analytics\Exception\AnalyticsRangeException
     * @throws \Dantweb\PeroxidAnalytics\Analytics\Exception\AnalyticsRankingException
     */
    public function topProducts(
        DateTimeInterface $from,
        DateTimeInterface $to,
        int $limit,
        AdminAnalyticsRankMeasure $measure
    ): AdminAnalyticsRanking;

    /**
     * The best selling categories of the period: a sale counts for the main category of its product only (sales of a
     * product without one are a line "No category"). `limit` is 1 to 20.
     *
     * @throws \Dantweb\PeroxidAnalytics\Analytics\Exception\AnalyticsRangeException
     * @throws \Dantweb\PeroxidAnalytics\Analytics\Exception\AnalyticsRankingException
     */
    public function topCategories(
        DateTimeInterface $from,
        DateTimeInterface $to,
        int $limit,
        AdminAnalyticsRankMeasure $measure
    ): AdminAnalyticsRanking;

    /**
     * The share that the period's top `limit` products hold in every bucket of the complete calendar.
     *
     * @throws \Dantweb\PeroxidAnalytics\Analytics\Exception\AnalyticsRangeException
     * @throws \Dantweb\PeroxidAnalytics\Analytics\Exception\AnalyticsRankingException
     */
    public function topShareSeries(
        DateTimeInterface $from,
        DateTimeInterface $to,
        AdminAnalyticsGranularity $granularity,
        int $limit,
        AdminAnalyticsRankMeasure $measure
    ): AdminAnalyticsShareSeries;
}
