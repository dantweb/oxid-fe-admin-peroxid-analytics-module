<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\DataType;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

/**
 * One bucket of the share curve: what the period's top products hold in it, what all products hold, and the share.
 */
#[Type(name: 'AdminAnalyticsShareBucket')]
final class AdminAnalyticsShareBucket
{
    public function __construct(
        private readonly string $key,
        private readonly float $value,
        private readonly float $total,
        private readonly float $share
    ) {
    }

    /** `YYYY-MM-DD` of the day or of the ISO Monday; `total` for the totals of the period. */
    #[Field]
    public function key(): string
    {
        return $this->key;
    }

    /** The measure of the top products in the bucket. */
    #[Field]
    public function value(): float
    {
        return $this->value;
    }

    /** The measure of all products in the bucket. */
    #[Field]
    public function total(): float
    {
        return $this->total;
    }

    /** Percent, one decimal; 0 for a bucket without sales. */
    #[Field]
    public function share(): float
    {
        return $this->share;
    }
}
