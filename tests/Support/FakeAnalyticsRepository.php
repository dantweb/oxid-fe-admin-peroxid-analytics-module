<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Support;

use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\AnalyticsRepositoryInterface;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\DailyFigures;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\OrderCountFigures;
use Dantweb\PeroxidAnalytics\Analytics\Service\AnalyticsRange;

final class FakeAnalyticsRepository implements AnalyticsRepositoryInterface
{
    public ?AnalyticsRange $askedFor = null;

    /**
     * @param list<DailyFigures> $figures
     */
    public function __construct(private readonly array $figures, private readonly ?OrderCountFigures $counts = null)
    {
    }

    public function orderCounts(AnalyticsRange $range): OrderCountFigures
    {
        $this->askedFor = $range;

        return $this->counts ?? new OrderCountFigures(0, 0, 0, 0);
    }

    public function perDay(AnalyticsRange $range): array
    {
        $this->askedFor = $range;

        return $this->figures;
    }
}
