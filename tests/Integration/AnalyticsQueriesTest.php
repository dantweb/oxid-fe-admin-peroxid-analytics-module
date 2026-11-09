<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Integration;

use Dantweb\PeroxidAnalytics\Authorization\AnalyticsRightsProvider;
use Dantweb\PeroxidAnalytics\Tests\Support\AdminSurfaceTestCase;
use OxidEsales\GraphQL\Admin\Authorization\Rights;

/**
 * The whole stack with a real admin (oxid-admin-graphql-api's test surface: a temporary admin, his roles, a session,
 * a token): the right, the refusals, and the numbers of two orders and one customer planted on known days.
 */
final class AnalyticsQueriesTest extends AdminSurfaceTestCase
{
    private const ORDER_A = 'pxanalytics-order-a';
    private const ORDER_B = 'pxanalytics-order-b';
    private const ORDER_CANCELLED = 'pxanalytics-order-c';
    private const CUSTOMER = 'pxanalytics-customer';

    private const QUERY = <<<'GRAPHQL'
        query ($from: DateTime!, $to: DateTime!, $granularity: AdminAnalyticsGranularity!) {
          adminAnalyticsSeries(from: $from, to: $to, granularity: $granularity) {
            currency granularity
            buckets { key orders sales customers ordersPerCustomer salesPerCustomer }
            totals { key orders sales customers }
          }
        }
        GRAPHQL;

    private const VARIABLES = ['from' => '2031-03-03', 'to' => '2031-03-05', 'granularity' => 'DAILY'];

    public function tearDown(): void
    {
        $this->connection()->executeStatement(
            'DELETE FROM oxorder WHERE OXID IN (?, ?, ?)',
            [self::ORDER_A, self::ORDER_B, self::ORDER_CANCELLED]
        );
        $this->connection()->executeStatement('DELETE FROM oxuser WHERE OXID = ?', [self::CUSTOMER]);
        parent::tearDown();
    }

    public function testNoTokenIsRefused(): void
    {
        $this->assertSame(
            'admin_session_required',
            $this->errorCodeOf($this->query(self::QUERY, self::VARIABLES))
        );
    }

    public function testAnAdminWithoutTheAnalyticsRightIsRefused(): void
    {
        $this->actorWithRights(Rights::ORDER_VIEW);

        $this->assertSame('permission_denied', $this->errorCodeOf($this->query(self::QUERY, self::VARIABLES)));
    }

    public function testAnAdminWithTheRightReadsTheBucketsOfTheShopCancelledOrdersExcluded(): void
    {
        $this->actorWithRights(AnalyticsRightsProvider::VIEW);
        $this->plantOrder(self::ORDER_A, '2031-03-03 10:00:00', 40.0);
        $this->plantOrder(self::ORDER_B, '2031-03-05 23:30:00', 5.5);
        $this->plantOrder(self::ORDER_CANCELLED, '2031-03-04 12:00:00', 999.0, cancelled: true);
        $this->plantCustomer(self::CUSTOMER, '2031-03-05 08:00:00');

        $result = $this->query(self::QUERY, self::VARIABLES);

        $this->assertSame(200, $result['status']);
        $series = $result['body']['data']['adminAnalyticsSeries'];
        $this->assertSame('DAILY', $series['granularity']);
        $this->assertNotSame('', $series['currency']);
        $this->assertSame(
            [
                self::bucket('2031-03-03', 1, 40.0, 0, 0.0, 0.0),
                self::bucket('2031-03-04', 0, 0.0, 0, 0.0, 0.0),
                self::bucket('2031-03-05', 1, 5.5, 1, 1.0, 5.5),
            ],
            $series['buckets']
        );
        $this->assertSame(['key' => 'total', 'orders' => 2, 'sales' => 45.5, 'customers' => 1], $series['totals']);
    }

    public function testWeeklyAndAnInvalidPeriod(): void
    {
        $this->actorWithRights(AnalyticsRightsProvider::VIEW);
        $this->plantOrder(self::ORDER_A, '2031-03-03 10:00:00', 40.0);

        $weekly = $this->query(self::QUERY, self::VARIABLES + ['granularity' => 'WEEKLY']);
        $reversed = $this->query(self::QUERY, ['from' => '2031-03-05', 'to' => '2031-03-03', 'granularity' => 'DAILY']);

        $buckets = $weekly['body']['data']['adminAnalyticsSeries']['buckets'];
        $this->assertSame(['2031-03-03'], array_column($buckets, 'key'));
        $this->assertSame(1, $weekly['body']['data']['adminAnalyticsSeries']['totals']['orders']);
        $this->assertSame('analytics_range_invalid', $this->errorCodeOf($reversed));
    }

    /**
     * @return array<string, string|int|float>
     */
    private static function bucket(
        string $key,
        int $orders,
        float $sales,
        int $customers,
        float $ordersPerCustomer,
        float $salesPerCustomer
    ): array {
        return [
            'key' => $key,
            'orders' => $orders,
            'sales' => $sales,
            'customers' => $customers,
            'ordersPerCustomer' => $ordersPerCustomer,
            'salesPerCustomer' => $salesPerCustomer,
        ];
    }

    private function plantOrder(string $id, string $date, float $total, bool $cancelled = false): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO oxorder (OXID, OXSHOPID, OXUSERID, OXORDERDATE, OXORDERNR, OXTOTALORDERSUM, OXTOTALBRUTSUM,'
            . ' OXCURRENCY, OXCURRATE, OXSTORNO, OXPAYMENTTYPE) VALUES (?, 1, ?, ?, 0, ?, ?, ?, 1, ?, ?)',
            [$id, 'oxdefaultadmin', $date, $total, $total, 'EUR', $cancelled ? 1 : 0, 'oxidinvoice']
        );
    }

    private function plantCustomer(string $id, string $created): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO oxuser (OXID, OXACTIVE, OXRIGHTS, OXSHOPID, OXUSERNAME, OXPASSWORD, OXCREATE, OXREGISTER)'
            . " VALUES (?, 1, 'user', 1, ?, '', ?, ?)",
            [$id, $id . '@example.invalid', $created, $created]
        );
    }
}
