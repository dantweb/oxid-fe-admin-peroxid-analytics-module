<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Service;

use DateTimeInterface;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsGranularity;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsSeries;

interface AnalyticsReaderInterface
{
    /**
     * @throws \Dantweb\PeroxidAnalytics\Analytics\Exception\AnalyticsRangeException
     */
    public function series(
        DateTimeInterface $from,
        DateTimeInterface $to,
        AdminAnalyticsGranularity $granularity
    ): AdminAnalyticsSeries;
}
