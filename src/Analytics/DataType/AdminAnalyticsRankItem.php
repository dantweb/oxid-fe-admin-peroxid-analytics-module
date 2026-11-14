<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\DataType;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

/**
 * One line of a ranking: its place, the product or category it stands for, its value in the unit of the measure and
 * its share of the whole in percent (one decimal).
 */
#[Type(name: 'AdminAnalyticsRankItem')]
final class AdminAnalyticsRankItem
{
    public function __construct(
        private readonly int $rank,
        private readonly ?string $entityId,
        private readonly ?string $number,
        private readonly string $title,
        private readonly float $value,
        private readonly float $share
    ) {
    }

    /** 1 for the best. */
    #[Field]
    public function rank(): int
    {
        return $this->rank;
    }

    /** The product or category id; null for the "No category" line and nothing a client could open. */
    #[Field]
    public function entityId(): ?string
    {
        return $this->entityId;
    }

    /** The article number of a product (the stored one when the product was deleted); null for a category. */
    #[Field]
    public function number(): ?string
    {
        return $this->number;
    }

    /** In the language of the request; the order item's stored title for a product that was deleted since. */
    #[Field]
    public function title(): string
    {
        return $this->title;
    }

    #[Field]
    public function value(): float
    {
        return $this->value;
    }

    /** Percent of the total of the period, one decimal; all items and "others" add up to 100. */
    #[Field]
    public function share(): float
    {
        return $this->share;
    }
}
