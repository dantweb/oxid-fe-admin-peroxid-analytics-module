<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Infrastructure;

use Dantweb\PeroxidAnalytics\Analytics\Service\AnalyticsRange;

/**
 * The per-day figures of the shop of the request: orders and their gross totals (cancelled orders excluded),
 * and the customers who registered. Aggregated in the database; no row of an order or a customer leaves it.
 */
interface AnalyticsRepositoryInterface
{
    /**
     * @return list<DailyFigures> one entry per day that has data, in any order
     */
    public function perDay(AnalyticsRange $range): array;
}
