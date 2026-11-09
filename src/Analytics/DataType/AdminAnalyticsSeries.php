<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\DataType;

use DateTimeImmutable;
use DateTimeInterface;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

/**
 * The answer of `adminAnalyticsSeries`: every bucket of the period in order (empty ones included, so the client
 * draws a complete curve), and the totals of the period.
 */
#[Type(name: 'AdminAnalyticsSeries')]
final class AdminAnalyticsSeries
{
    /**
     * @param list<AdminAnalyticsBucket> $buckets
     */
    public function __construct(
        private readonly DateTimeImmutable $from,
        private readonly DateTimeImmutable $to,
        private readonly AdminAnalyticsGranularity $granularity,
        private readonly string $currency,
        private readonly array $buckets,
        private readonly AdminAnalyticsBucket $totals
    ) {
    }

    /** The first day of the period, at midnight. */
    #[Field]
    public function from(): DateTimeInterface
    {
        return $this->from;
    }

    /** The last day of the period, at midnight (the whole day counts). */
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

    /** ISO code of the shop's currency the sales are summed in. */
    #[Field]
    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * @return AdminAnalyticsBucket[]
     */
    #[Field]
    public function buckets(): array
    {
        return $this->buckets;
    }

    #[Field]
    public function totals(): AdminAnalyticsBucket
    {
        return $this->totals;
    }
}
