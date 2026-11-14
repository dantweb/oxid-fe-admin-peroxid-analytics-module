<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\DataType;

/**
 * What a ranking counts: gross sales of the order items, units sold (item amounts), or the number of orders that
 * contain the product or the category.
 */
enum AdminAnalyticsRankMeasure: string
{
    case SALES = 'SALES';
    case UNITS = 'UNITS';
    case ORDERS = 'ORDERS';
}
