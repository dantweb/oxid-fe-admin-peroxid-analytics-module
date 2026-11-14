<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Infrastructure;

/**
 * One ranked product or category as the repository found it. `entityId` is null for sales without a main category.
 * A value object, not a service.
 */
final class RankedFigure
{
    public function __construct(
        public readonly ?string $entityId,
        public readonly ?string $number,
        public readonly string $title,
        public readonly float $value
    ) {
    }
}
