<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\DataType;

/**
 * The width of a bucket: a calendar day, or an ISO week (Monday to Sunday) named by its Monday.
 */
enum AdminAnalyticsGranularity: string
{
    case DAILY = 'DAILY';
    case WEEKLY = 'WEEKLY';
}
