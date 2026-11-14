<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Infrastructure;

/**
 * One ranked product or category as the repository found it. `entityId` is null for sales without a main category;
 * `exists` is false for a product that was deleted after the sale.
 * A value object, not a service.
 */
final class RankedFigure
{
    public function __construct(
        public readonly ?string $entityId,
        public readonly ?string $number,
        public readonly string $title,
        public readonly float $value,
        /** false for a product that was deleted after the sale: no page to link to */
        public readonly bool $exists
    ) {
    }

    public static function of(?string $entityId, ?string $number, string $title, float $value): self
    {
        return new self($entityId, $number, $title, $value, true);
    }

    /** A product that was deleted after the sale: it keeps the title of the order item, but has no page. */
    public static function gone(string $entityId, ?string $number, string $title, float $value): self
    {
        return new self($entityId, $number, $title, $value, false);
    }
}
