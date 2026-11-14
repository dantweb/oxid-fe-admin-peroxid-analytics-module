<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Integration;

use Dantweb\PeroxidAnalytics\Authorization\AnalyticsRightsProvider;
use Dantweb\PeroxidAnalytics\Tests\Support\AdminSurfaceTestCase;
use OxidEsales\GraphQL\Admin\Authorization\Rights;

/**
 * The three ranking queries through the whole stack with a real admin: the refusals, the error codes, and the numbers
 * of planted order items (products no longer in the catalogue, so the stored titles are the answer).
 */
final class TopRankingQueriesTest extends AdminSurfaceTestCase
{
    private const ORDER = 'pxtop-order';
    private const ORDER_CANCELLED = 'pxtop-order-c';

    private const PRODUCTS = <<<'GRAPHQL'
        query ($from: DateTime!, $to: DateTime!, $limit: Int!, $measure: AdminAnalyticsRankMeasure!) {
          adminAnalyticsTopProducts(from: $from, to: $to, limit: $limit, measure: $measure) {
            measure total topTotal topShare others othersShare
            items { rank entityId number title value share }
          }
        }
        GRAPHQL;

    private const CATEGORIES = <<<'GRAPHQL'
        query ($from: DateTime!, $to: DateTime!, $limit: Int!, $measure: AdminAnalyticsRankMeasure!) {
          adminAnalyticsTopCategories(from: $from, to: $to, limit: $limit, measure: $measure) {
            items { rank entityId title value share }
          }
        }
        GRAPHQL;

    private const SHARE = <<<'GRAPHQL'
        query (
          $from: DateTime!, $to: DateTime!, $granularity: AdminAnalyticsGranularity!, $limit: Int!,
          $measure: AdminAnalyticsRankMeasure!
        ) {
          adminAnalyticsTopShareSeries(
            from: $from, to: $to, granularity: $granularity, limit: $limit, measure: $measure
          ) {
            buckets { key value total share }
            totals { key value total share }
          }
        }
        GRAPHQL;

    private const VARIABLES = ['from' => '2031-07-01', 'to' => '2031-07-02', 'limit' => 5, 'measure' => 'SALES'];

    public function tearDown(): void
    {
        $ids = [self::ORDER, self::ORDER_CANCELLED];
        $this->connection()->executeStatement('DELETE FROM oxorderarticles WHERE OXORDERID IN (?, ?)', $ids);
        $this->connection()->executeStatement('DELETE FROM oxorder WHERE OXID IN (?, ?)', $ids);
        parent::tearDown();
    }

    public function testNoTokenIsRefusedOnEveryQuery(): void
    {
        $this->assertSame('admin_session_required', $this->errorCodeOf($this->query(self::PRODUCTS, self::VARIABLES)));
        $this->assertSame(
            'admin_session_required',
            $this->errorCodeOf($this->query(self::CATEGORIES, self::VARIABLES))
        );
    }

    public function testAnAdminWithoutTheAnalyticsRightIsRefusedOnEveryQuery(): void
    {
        $this->actorWithRights(Rights::ORDER_VIEW);

        $this->assertSame('permission_denied', $this->errorCodeOf($this->query(self::PRODUCTS, self::VARIABLES)));
        $this->assertSame('permission_denied', $this->errorCodeOf($this->query(self::CATEGORIES, self::VARIABLES)));
        $this->assertSame('permission_denied', $this->errorCodeOf($this->query(self::SHARE, self::shareVariables())));
    }

    public function testTheTopProductsOfPlantedItemsCancelledOnesLeftOut(): void
    {
        $this->actorWithRights(AnalyticsRightsProvider::VIEW);
        $this->plantOrder();

        $result = $this->query(self::PRODUCTS, self::VARIABLES);

        $this->assertSame(200, $result['status']);
        $ranking = $result['body']['data']['adminAnalyticsTopProducts'];
        $this->assertSame('SALES', $ranking['measure']);
        $this->assertSame([40.0, 40.0, 100.0], [$ranking['total'], $ranking['topTotal'], $ranking['topShare']]);
        $this->assertSame(['Gone Hat', 'Gone Cap'], array_column($ranking['items'], 'title'));
        $this->assertSame([75.0, 25.0], array_column($ranking['items'], 'share'));
    }

    public function testTheCategoriesAndTheShareSeriesAnswerWithTheCompleteCalendar(): void
    {
        $this->actorWithRights(AnalyticsRightsProvider::VIEW);
        $this->plantOrder();

        $categories = $this->query(self::CATEGORIES, self::VARIABLES);
        $share = $this->query(self::SHARE, self::shareVariables());

        $this->assertSame(
            [['rank' => 1, 'entityId' => null, 'title' => 'No category', 'value' => 40.0, 'share' => 100.0]],
            $categories['body']['data']['adminAnalyticsTopCategories']['items']
        );
        $this->assertSame(
            ['2031-07-01', '2031-07-02'],
            array_column($share['body']['data']['adminAnalyticsTopShareSeries']['buckets'], 'key')
        );
    }

    public function testALimitOutsideTheBoundsAndAnInvalidPeriodCarryTheirErrorCodes(): void
    {
        $this->actorWithRights(AnalyticsRightsProvider::VIEW);

        $tooMany = $this->query(self::PRODUCTS, ['limit' => 21] + self::VARIABLES);
        $reversed = $this->query(self::PRODUCTS, ['from' => '2031-07-05', 'to' => '2031-07-01'] + self::VARIABLES);

        $this->assertSame('analytics_ranking_invalid', $this->errorCodeOf($tooMany));
        $this->assertSame('analytics_range_invalid', $this->errorCodeOf($reversed));
    }

    /**
     * @return array<string, string|int>
     */
    private static function shareVariables(): array
    {
        return self::VARIABLES + ['granularity' => 'DAILY'];
    }

    private function plantOrder(): void
    {
        $this->insertOrder(self::ORDER, 0);
        $this->insertOrder(self::ORDER_CANCELLED, 1);
        $this->insertLine(self::ORDER, 'pxtop-hat', 'Gone Hat', 3, 30.0);
        $this->insertLine(self::ORDER, 'pxtop-cap', 'Gone Cap', 1, 10.0);
        $this->insertLine(self::ORDER_CANCELLED, 'pxtop-hat', 'Gone Hat', 9, 999.0);
    }

    private function insertOrder(string $id, int $cancelled): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO oxorder (OXID, OXSHOPID, OXUSERID, OXORDERDATE, OXORDERNR, OXTOTALORDERSUM, OXTOTALBRUTSUM,'
            . ' OXCURRENCY, OXCURRATE, OXSTORNO, OXPAYMENTTYPE) VALUES (?, 1, ?, ?, 0, 10, 10, ?, 1, ?, ?)',
            [$id, 'oxdefaultadmin', '2031-07-01 10:00:00', 'EUR', $cancelled, 'oxidinvoice']
        );
    }

    private function insertLine(string $orderId, string $productId, string $title, int $amount, float $gross): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO oxorderarticles (OXID, OXORDERID, OXAMOUNT, OXARTID, OXARTNUM, OXTITLE, OXBRUTPRICE)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$orderId . $productId, $orderId, $amount, $productId, strtoupper($productId), $title, $gross]
        );
    }
}
