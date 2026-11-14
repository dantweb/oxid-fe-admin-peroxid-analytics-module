<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\DataType;

use DateTimeImmutable;
use DateTimeInterface;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

/**
 * The answer of `adminAnalyticsTopShareSeries`: the share that the top N products of the whole period hold in every
 * bucket of the complete calendar, and the same share over the whole period.
 */
#[Type(name: 'AdminAnalyticsShareSeries')]
final class AdminAnalyticsShareSeries
{
    /**
     * @param list<AdminAnalyticsShareBucket> $buckets
     */
    public function __construct(
        private readonly DateTimeImmutable $from,
        private readonly DateTimeImmutable $to,
        private readonly AdminAnalyticsGranularity $granularity,
        private readonly AdminAnalyticsRankMeasure $measure,
        private readonly array $buckets,
        private readonly AdminAnalyticsShareBucket $totals
    ) {
    }

    #[Field]
    public function from(): DateTimeInterface
    {
        return $this->from;
    }

    #[Field]
    public function to(): DateTimeInterface
    {
        return $this->to;
    }

    #[Field]
    public function granularity(): AdminAnalyticsGranularity
    {
        return $this->granularity;
    }

    #[Field]
    public function measure(): AdminAnalyticsRankMeasure
    {
        return $this->measure;
    }

    /**
     * @return AdminAnalyticsShareBucket[]
     */
    #[Field]
    public function buckets(): array
    {
        return $this->buckets;
    }

    #[Field]
    public function totals(): AdminAnalyticsShareBucket
    {
        return $this->totals;
    }
}
