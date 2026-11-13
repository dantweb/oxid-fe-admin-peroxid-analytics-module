<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Infrastructure;

use Dantweb\PeroxidAnalytics\Analytics\Service\AnalyticsRange;
use OxidEsales\EshopCommunity\Internal\Framework\Database\ConnectionProviderInterface;
use OxidEsales\GraphQL\Admin\Shared\Infrastructure\ShopScope;

/**
 * Two GROUP BY queries, both confined to the shop of the request (graphql-admin's ShopScope):
 *  - oxorder: COUNT and SUM(OXTOTALORDERSUM) per DATE(OXORDERDATE), cancelled orders (OXSTORNO) excluded;
 *  - oxuser:  COUNT per DATE(OXCREATE) of customer accounts (OXRIGHTS = 'user'; admins are not customers).
 * The date columns hold the shop's local time, so a day is a calendar day of the shop.
 */
final class DbalAnalyticsRepository implements AnalyticsRepositoryInterface
{
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly ConnectionProviderInterface $connectionProvider,
        private readonly ShopScope $scope
    ) {
    }

    public function perDay(AnalyticsRange $range): array
    {
        $parameters = [
            'from' => $range->from()->format(self::DATE_FORMAT),
            'until' => $range->until()->format(self::DATE_FORMAT),
        ] + $this->scope->parameters();
        $connection = $this->connectionProvider->get();

        $figures = [];
        $orders = $connection->fetchAllAssociative(
            'SELECT DATE(OXORDERDATE) AS day, COUNT(*) AS orders, COALESCE(SUM(OXTOTALORDERSUM), 0) AS sales'
            . ' FROM oxorder WHERE ' . $this->scope->equals('OXSHOPID')
            . ' AND OXSTORNO = 0 AND OXORDERDATE >= :from AND OXORDERDATE < :until GROUP BY day',
            $parameters
        );
        foreach ($orders as $row) {
            $figures[(string) $row['day']] = [
                'orders' => (int) $row['orders'],
                'sales' => (float) $row['sales'],
                'customers' => 0,
            ];
        }
        $customers = $connection->fetchAllAssociative(
            'SELECT DATE(OXCREATE) AS day, COUNT(*) AS customers FROM oxuser WHERE ' . $this->scope->equals('OXSHOPID')
            . " AND OXRIGHTS = 'user' AND OXCREATE >= :from AND OXCREATE < :until GROUP BY day",
            $parameters
        );
        foreach ($customers as $row) {
            $day = (string) $row['day'];
            $figures[$day] ??= ['orders' => 0, 'sales' => 0.0, 'customers' => 0];
            $figures[$day]['customers'] = (int) $row['customers'];
        }

        $result = [];
        foreach ($figures as $day => $values) {
            $result[] = new DailyFigures($day, $values['orders'], $values['sales'], $values['customers']);
        }

        return $result;
    }

    public function orderCounts(AnalyticsRange $range): OrderCountFigures
    {
        $never = '0000-00-00 00:00:00';
        $row = $this->connectionProvider->get()->fetchAssociative(
            'SELECT COUNT(*) AS total, COALESCE(SUM(OXPAID <> :never), 0) AS paid,'
            . ' COALESCE(SUM(OXSENDDATE <> :never), 0) AS shipped, COALESCE(SUM(OXSTORNO = 1), 0) AS cancelled'
            . ' FROM oxorder WHERE ' . $this->scope->equals('OXSHOPID')
            . ' AND OXORDERDATE >= :from AND OXORDERDATE < :until',
            [
                'never' => $never,
                'from' => $range->from()->format(self::DATE_FORMAT),
                'until' => $range->until()->format(self::DATE_FORMAT),
            ] + $this->scope->parameters()
        ) ?: [];

        return new OrderCountFigures(
            (int) ($row['total'] ?? 0),
            (int) ($row['paid'] ?? 0),
            (int) ($row['shipped'] ?? 0),
            (int) ($row['cancelled'] ?? 0)
        );
    }
}
