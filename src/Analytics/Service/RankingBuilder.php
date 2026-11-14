<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Service;

use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRankItem;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRankMeasure;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRanking;
use Dantweb\PeroxidAnalytics\Analytics\Infrastructure\RankedFigure;

/**
 * Turns the top rows and the total of the repository into a ranking: the "others" remainder, and shares with one
 * decimal that add up to 100 (items and others together).
 */
final class RankingBuilder
{
    private const NO_CATEGORY = 'No category';

    private const DECIMALS = 2;

    public function __construct(private readonly ShareRounder $rounder)
    {
    }

    /**
     * @param list<RankedFigure> $figures
     */
    public function build(
        AdminAnalyticsRankMeasure $measure,
        string $currency,
        array $figures,
        float $rawTotal
    ): AdminAnalyticsRanking {
        $total = round($rawTotal, self::DECIMALS);
        $values = array_map(static fn (RankedFigure $figure): float => $figure->value, $figures);
        $topTotal = round(array_sum($values), self::DECIMALS);
        $others = max(0.0, round($total - $topTotal, self::DECIMALS));
        $shares = $this->rounder->percentages([...$values, $others], $total);
        $othersShare = (float) array_pop($shares);

        return new AdminAnalyticsRanking(
            $measure,
            $currency,
            $total,
            $topTotal,
            round(array_sum($shares), 1),
            $others,
            $othersShare,
            $this->items($figures, $shares)
        );
    }

    /**
     * @param list<RankedFigure> $figures
     * @param list<float> $shares
     * @return list<AdminAnalyticsRankItem>
     */
    private function items(array $figures, array $shares): array
    {
        $items = [];
        foreach ($figures as $index => $figure) {
            $items[] = new AdminAnalyticsRankItem(
                $index + 1,
                $figure->entityId,
                $figure->number,
                $figure->entityId === null ? self::NO_CATEGORY : $figure->title,
                round($figure->value, self::DECIMALS),
                $shares[$index]
            );
        }

        return $items;
    }
}
