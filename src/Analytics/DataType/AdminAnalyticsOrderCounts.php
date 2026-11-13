<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\DataType;

use DateTimeImmutable;
use DateTimeInterface;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

/**
 * The answer of `adminAnalyticsOrderCounts`: how many orders the shop got in the period, and how many of them are
 * paid, shipped and cancelled. Cancelled orders are part of `total` here (the series' sales leave them out).
 */
#[Type(name: 'AdminAnalyticsOrderCounts')]
final class AdminAnalyticsOrderCounts
{
    public function __construct(
        private readonly DateTimeImmutable $from,
        private readonly DateTimeImmutable $to,
        private readonly int $total,
        private readonly int $paid,
        private readonly int $shipped,
        private readonly int $cancelled
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

    /** Every order of the period, cancelled ones included. */
    #[Field]
    public function total(): int
    {
        return $this->total;
    }

    #[Field]
    public function paid(): int
    {
        return $this->paid;
    }

    #[Field]
    public function shipped(): int
    {
        return $this->shipped;
    }

    #[Field]
    public function cancelled(): int
    {
        return $this->cancelled;
    }
}
