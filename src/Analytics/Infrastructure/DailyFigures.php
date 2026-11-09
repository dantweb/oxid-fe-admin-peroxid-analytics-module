<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Infrastructure;

/**
 * What the repository found for one calendar day (`YYYY-MM-DD`). A value object, not a service.
 */
final class DailyFigures
{
    public function __construct(
        public readonly string $day,
        public readonly int $orders,
        public readonly float $sales,
        public readonly int $customers
    ) {
    }
}
