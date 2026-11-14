<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Support;

use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRankMeasure;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\RankedFigure;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\RankingRepositoryInterface;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\RankingSubject;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\ShareDayFigures;
use Dantweb\PeroxidAnalytics\Analytics\Service\AnalyticsRange;

final class FakeRankingRepository implements RankingRepositoryInterface
{
    public ?AnalyticsRange $askedFor = null;

    public ?AdminAnalyticsRankMeasure $measureAskedFor = null;

    public ?int $limitAskedFor = null;

    public ?RankingSubject $subjectAskedFor = null;

    /** @var list<string> */
    public array $productIdsAskedFor = [];

    /**
     * @param list<RankedFigure> $figures
     * @param list<ShareDayFigures> $days
     */
    public function __construct(
        private readonly array $figures,
        private readonly float $total,
        private readonly array $days = []
    ) {
    }

    public function top(
        RankingSubject $subject,
        AnalyticsRange $range,
        AdminAnalyticsRankMeasure $measure,
        int $limit
    ): array {
        $this->subjectAskedFor = $subject;
        $this->askedFor = $range;
        $this->measureAskedFor = $measure;
        $this->limitAskedFor = $limit;

        return $this->figures;
    }

    public function total(RankingSubject $subject, AnalyticsRange $range, AdminAnalyticsRankMeasure $measure): float
    {
        return $this->total;
    }

    public function shareByDay(AnalyticsRange $range, AdminAnalyticsRankMeasure $measure, array $productIds): array
    {
        $this->productIdsAskedFor = $productIds;

        return $this->days;
    }
}
