<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Support;

use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\AnalyticsRepositoryInterface;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\DailyFigures;
use Dantweb\PeroxidAnalytics\Analytics\Service\AnalyticsRange;

final class FakeAnalyticsRepository implements AnalyticsRepositoryInterface
{
    public ?AnalyticsRange $askedFor = null;

    /**
     * @param list<DailyFigures> $figures
     */
    public function __construct(private readonly array $figures)
    {
    }

    public function perDay(AnalyticsRange $range): array
    {
        $this->askedFor = $range;

        return $this->figures;
    }
}
