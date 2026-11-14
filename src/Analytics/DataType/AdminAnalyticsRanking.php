<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\DataType;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

/**
 * The answer of `adminAnalyticsTopProducts` and `adminAnalyticsTopCategories`: the top N with their shares, the
 * remainder ("others") so that the whole is shown, and the share of the top N in the total.
 */
#[Type(name: 'AdminAnalyticsRanking')]
final class AdminAnalyticsRanking
{
    /**
     * @param list<AdminAnalyticsRankItem> $items
     */
    public function __construct(
        private readonly AdminAnalyticsRankMeasure $measure,
        private readonly string $currency,
        private readonly float $total,
        private readonly float $topTotal,
        private readonly float $topShare,
        private readonly float $others,
        private readonly float $othersShare,
        private readonly array $items
    ) {
    }

    #[Field]
    public function measure(): AdminAnalyticsRankMeasure
    {
        return $this->measure;
    }

    /** ISO code of the shop's currency; the unit of the values when the measure is SALES. */
    #[Field]
    public function currency(): string
    {
        return $this->currency;
    }

    /** The measure over all products (or categories) of the period. */
    #[Field]
    public function total(): float
    {
        return $this->total;
    }

    /** The measure over the items of the list. */
    #[Field]
    public function topTotal(): float
    {
        return $this->topTotal;
    }

    /** Percent of the total that the items hold (the sum of their shares). */
    #[Field]
    public function topShare(): float
    {
        return $this->topShare;
    }

    /** The measure of everything outside the list. */
    #[Field]
    public function others(): float
    {
        return $this->others;
    }

    #[Field]
    public function othersShare(): float
    {
        return $this->othersShare;
    }

    /**
     * @return AdminAnalyticsRankItem[]
     */
    #[Field]
    public function items(): array
    {
        return $this->items;
    }
}
