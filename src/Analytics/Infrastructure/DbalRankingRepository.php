<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Infrastructure;

use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRankMeasure;
use Dantweb\PeroxidAnalytics\Analytics\Service\AnalyticsRange;
use OxidEsales\EshopCommunity\Internal\Framework\Database\ConnectionProviderInterface;
use OxidEsales\GraphQL\Admin\Shared\Infrastructure\ShopScope;

/**
 * Runs the statements of RankingSql, confined to the shop of the request (ShopScope). Rows of orders, customers and
 * items never leave the database: only the top lines, one sum and one row per day.
 *
 * Rules: cancelled orders (`oxorder.OXSTORNO`) and cancelled items (`oxorderarticles.OXSTORNO`) are left out; sales
 * are the gross item totals (`OXBRUTPRICE`), units the item amounts, orders the distinct orders.
 */
final class DbalRankingRepository implements RankingRepositoryInterface
{
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly ConnectionProviderInterface $connectionProvider,
        private readonly ShopScope $scope,
        private readonly RankingSql $sql
    ) {
    }

    public function top(
        RankingSubject $subject,
        AnalyticsRange $range,
        AdminAnalyticsRankMeasure $measure,
        int $limit
    ): array {
        $rows = $this->connectionProvider->get()->fetchAllAssociative(
            $this->sql->top($subject, $measure, $limit),
            $this->parameters($range)
        );

        return array_values(array_map(
            static fn (array $row): RankedFigure => new RankedFigure(
                $row['entityId'] === null ? null : (string) $row['entityId'],
                $row['number'] === null ? null : (string) $row['number'],
                (string) $row['title'],
                (float) $row['value']
            ),
            $rows
        ));
    }

    public function total(RankingSubject $subject, AnalyticsRange $range, AdminAnalyticsRankMeasure $measure): float
    {
        return (float) $this->connectionProvider->get()->fetchOne(
            $this->sql->total($subject, $measure),
            $this->parameters($range)
        );
    }

    public function shareByDay(AnalyticsRange $range, AdminAnalyticsRankMeasure $measure, array $productIds): array
    {
        $rows = $this->connectionProvider->get()->fetchAllAssociative(
            $this->sql->shareByDay($measure, $productIds),
            $this->parameters($range) + $this->sql->idParameters($productIds)
        );

        return array_values(array_map(
            static fn (array $row): ShareDayFigures => new ShareDayFigures(
                (string) $row['day'],
                (float) $row['topValue'],
                (float) $row['totalValue']
            ),
            $rows
        ));
    }

    /**
     * @return array<string, string|int>
     */
    private function parameters(AnalyticsRange $range): array
    {
        return [
            RankingSql::PARAMETER_FROM => $range->from()->format(self::DATE_FORMAT),
            RankingSql::PARAMETER_UNTIL => $range->until()->format(self::DATE_FORMAT),
        ] + $this->scope->parameters();
    }
}
