<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Infrastructure;

/**
 * One calendar day of the share curve: the measure of the chosen top products and of all products. A value object, not
 * a service.
 */
final class ShareDayFigures
{
    public function __construct(
        public readonly string $day,
        public readonly float $top,
        public readonly float $total
    ) {
    }
}
