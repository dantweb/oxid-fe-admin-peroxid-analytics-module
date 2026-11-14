<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Analytics\Infrastructure;

use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRankMeasure;
use OxidEsales\GraphQL\Admin\Shared\Filter\ListContextInterface;
use OxidEsales\GraphQL\Admin\Shared\Filter\ViewNameResolverInterface;
use OxidEsales\GraphQL\Admin\Shared\Infrastructure\ShopScope;

/**
 * The statements of the rankings. Everything is built from one derived table of order lines ("lines"): the items of the
 * non-cancelled orders of the shop and period, each already attributed to its product (a variant to its parent) and,
 * for categories, to the main category of that product (the assignment with the lowest OXTIME, the classic "default
 * category"). The grouping, the sums and the sorting stay in the database.
 *
 * Parameters every statement needs: `from`, `until` and ShopScope's (see RankingParameters).
 */
final class RankingSql
{
    public const PARAMETER_FROM = 'from';
    public const PARAMETER_UNTIL = 'until';
    public const PARAMETER_ID_PREFIX = 'productId';

    private const PRODUCT_OF_LINE = "COALESCE(NULLIF(a.OXPARENTID, ''), oa.OXARTID)";

    public function __construct(
        private readonly ShopScope $scope,
        private readonly ViewNameResolverInterface $views,
        private readonly ListContextInterface $context
    ) {
    }

    public function top(
        RankingSubject $subject,
        AdminAnalyticsRankMeasure $measure,
        int $limit
    ): string {
        $joins = $subject === RankingSubject::PRODUCTS ? $this->productJoin() : $this->categoryJoin();

        return 'SELECT g.entityId AS entityId, ' . $joins['number'] . ' AS number, ' . $joins['title'] . ' AS title,'
            . ' g.value AS value FROM (' . $this->grouped($subject, $measure) . ') g ' . $joins['from']
            . ' ORDER BY g.value DESC, g.entityId IS NULL, title, g.entityId LIMIT ' . $limit;
    }

    public function total(RankingSubject $subject, AdminAnalyticsRankMeasure $measure): string
    {
        return 'SELECT COALESCE(SUM(g.value), 0) FROM (' . $this->grouped($subject, $measure) . ') g';
    }

    /**
     * @param list<string> $productIds
     */
    public function shareByDay(AdminAnalyticsRankMeasure $measure, array $productIds): string
    {
        $inTop = 'l.productId IN (' . implode(', ', $this->idPlaceholders($productIds)) . ')';
        [$top, $all] = match ($measure) {
            AdminAnalyticsRankMeasure::SALES => ['SUM(IF(' . $inTop . ', l.gross, 0))', 'SUM(l.gross)'],
            AdminAnalyticsRankMeasure::UNITS => ['SUM(IF(' . $inTop . ', l.amount, 0))', 'SUM(l.amount)'],
            AdminAnalyticsRankMeasure::ORDERS => [
                'COUNT(DISTINCT IF(' . $inTop . ', ' . $this->orderProductPair() . ', NULL))',
                'COUNT(DISTINCT ' . $this->orderProductPair() . ')',
            ],
        };

        return 'SELECT l.orderDay AS day, ' . $top . ' AS topValue, ' . $all . ' AS totalValue FROM ('
            . $this->lines(false) . ') l GROUP BY l.orderDay';
    }

    /**
     * @param list<string> $productIds
     * @return array<string, string>
     */
    public function idParameters(array $productIds): array
    {
        $parameters = [];
        foreach ($productIds as $index => $productId) {
            $parameters[self::PARAMETER_ID_PREFIX . $index] = $productId;
        }

        return $parameters;
    }

    /**
     * @param list<string> $productIds
     * @return list<string>
     */
    private function idPlaceholders(array $productIds): array
    {
        return array_map(
            static fn (int $index): string => ':' . self::PARAMETER_ID_PREFIX . $index,
            array_keys($productIds)
        );
    }

    private function orderProductPair(): string
    {
        return "CONCAT(l.orderId, '|', l.productId)";
    }

    private function grouped(RankingSubject $subject, AdminAnalyticsRankMeasure $measure): string
    {
        $aggregate = match ($measure) {
            AdminAnalyticsRankMeasure::SALES => 'SUM(l.gross)',
            AdminAnalyticsRankMeasure::UNITS => 'SUM(l.amount)',
            AdminAnalyticsRankMeasure::ORDERS => 'COUNT(DISTINCT l.orderId)',
        };
        if ($subject === RankingSubject::CATEGORIES) {
            return 'SELECT l.categoryId AS entityId, ' . $aggregate . ' AS value FROM (' . $this->lines(true)
                . ') l GROUP BY l.categoryId';
        }

        return 'SELECT l.productId AS entityId, MAX(l.lineNumber) AS lineNumber, MAX(l.lineTitle) AS lineTitle, '
            . $aggregate . ' AS value FROM (' . $this->lines(false) . ') l GROUP BY l.productId';
    }

    /**
     * @return array{from: string, number: string, title: string}
     */
    private function productJoin(): array
    {
        $view = $this->view('oxarticles');

        return [
            'from' => 'LEFT JOIN ' . $view . ' p ON p.OXID = g.entityId',
            'number' => "COALESCE(NULLIF(p.OXARTNUM, ''), g.lineNumber)",
            'title' => "COALESCE(NULLIF(p.OXTITLE, ''), g.lineTitle)",
        ];
    }

    /**
     * @return array{from: string, number: string, title: string}
     */
    private function categoryJoin(): array
    {
        return [
            'from' => 'LEFT JOIN ' . $this->view('oxcategories') . ' c ON c.OXID = g.entityId'
                . ' LEFT JOIN oxcategories b ON b.OXID = g.entityId',
            'number' => 'NULL',
            'title' => "COALESCE(NULLIF(c.OXTITLE, ''), b.OXTITLE, '')",
        ];
    }

    private function lines(bool $withCategory): string
    {
        $category = $withCategory ? ', ' . $this->mainCategory() . ' AS categoryId' : '';

        return 'SELECT oa.OXORDERID AS orderId, DATE(o.OXORDERDATE) AS orderDay, ' . self::PRODUCT_OF_LINE
            . ' AS productId, oa.OXAMOUNT AS amount, oa.OXBRUTPRICE AS gross, oa.OXARTNUM AS lineNumber,'
            . ' oa.OXTITLE AS lineTitle' . $category
            . ' FROM oxorderarticles oa JOIN oxorder o ON o.OXID = oa.OXORDERID'
            . ' LEFT JOIN oxarticles a ON a.OXID = oa.OXARTID WHERE ' . $this->scope->equals('o.OXSHOPID')
            . ' AND o.OXSTORNO = 0 AND oa.OXSTORNO = 0 AND o.OXORDERDATE >= :' . self::PARAMETER_FROM
            . ' AND o.OXORDERDATE < :' . self::PARAMETER_UNTIL;
    }

    /** The product's category assignment with the lowest OXTIME (ties by id), if its category still exists. */
    private function mainCategory(): string
    {
        return '(SELECT o2c.OXCATNID FROM oxobject2category o2c JOIN oxcategories mc ON mc.OXID = o2c.OXCATNID'
            . ' WHERE o2c.OXOBJECTID = ' . self::PRODUCT_OF_LINE . ' ORDER BY o2c.OXTIME, o2c.OXID LIMIT 1)';
    }

    private function view(string $table): string
    {
        return $this->views->resolve($table, $this->context->languageId(), $this->scope->id());
    }
}
