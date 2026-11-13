<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Infrastructure;

/** Order counts of a period as the repository reads them (one query, no rows loaded). */
final class OrderCountFigures
{
    public function __construct(
        public readonly int $total,
        public readonly int $paid,
        public readonly int $shipped,
        public readonly int $cancelled
    ) {
    }
}
