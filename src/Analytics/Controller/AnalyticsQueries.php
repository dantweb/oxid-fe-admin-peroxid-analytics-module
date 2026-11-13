<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Controller;

use DateTimeInterface;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsGranularity;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsOrderCounts;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsSeries;
use Dantweb\PeroxidAnalytics\Analytics\Service\AnalyticsReaderInterface;
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
    public function __construct(private readonly AnalyticsReaderInterface $reader)
    {
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
}
