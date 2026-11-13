<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Service;

use DateTimeInterface;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsGranularity;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsOrderCounts;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsSeries;

interface AnalyticsReaderInterface
{
    /**
     * @throws \Dantweb\PeroxidAnalytics\Analytics\Exception\AnalyticsRangeException
     */
    public function series(
        DateTimeInterface $from,
        DateTimeInterface $to,
        AdminAnalyticsGranularity $granularity
    ): AdminAnalyticsSeries;

    /**
     * How many orders the shop got between `from` and `to` (whole days, both included; at most 366 days): total
     * (cancelled included), paid, shipped, cancelled.
     *
     * @throws \Dantweb\PeroxidAnalytics\Analytics\Exception\AnalyticsRangeException
     */
    public function orderCounts(DateTimeInterface $from, DateTimeInterface $to): AdminAnalyticsOrderCounts;
}
