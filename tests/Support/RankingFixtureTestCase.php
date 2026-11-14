<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Support;

use Dantweb\PeroxidAnalytics\Analytics\Service\RankingReaderInterface;
use Doctrine\DBAL\Connection;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Database\ConnectionProviderInterface;
use PHPUnit\Framework\TestCase;

/**
 * The reader on the real database inside a transaction that is rolled back. The planted period is 2031-06-01 to
 * 2031-06-03:
 *
 *   Alpha (parent, variants Alpha-S and Alpha-M), main category Shirts (also assigned to Shoes later)
 *   Beta, main category Shoes; Gamma without a category; "Gone", a product deleted after it was sold.
 *   order 1 (06-01):  Alpha-S 2 units 20.0 | Beta 1 unit 30.0
 *   order 2 (06-02):  Alpha-M 1 unit 10.0 | Gamma 5 units 5.0 | Gone 1 unit 1.0 | Beta 9 units 90.0 (cancelled item)
 *   order 3 (06-02):  cancelled order, Gamma 100 units 999.0
 *   order 4: shop 2 (06-01) | order 5: 2031-06-04, outside the period
 */
abstract class RankingFixtureTestCase extends TestCase
{
    protected const FROM = '2031-06-01';
    protected const TO = '2031-06-03';

    protected Connection $connection;

    protected function setUp(): void
    {
        $this->connection = ContainerFactory::getInstance()->getContainer()
            ->get(ConnectionProviderInterface::class)->get();
        $this->connection->beginTransaction();
        $this->plantCatalogue();
        $this->plantOrders();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
    }

    protected function reader(): RankingReaderInterface
    {
        return ContainerFactory::getInstance()->getContainer()->get(RankingReaderInterface::class);
    }

    private function plantCatalogue(): void
    {
        $this->product('pxr-alpha', '', 'ALPHA-0', 'Alpha');
        $this->product('pxr-alpha-s', 'pxr-alpha', 'ALPHA-S', 'Alpha');
        $this->product('pxr-alpha-m', 'pxr-alpha', 'ALPHA-M', 'Alpha');
        $this->product('pxr-beta', '', 'BETA-0', 'Beta');
        $this->product('pxr-gamma', '', 'GAMMA-0', 'Gamma');
        $this->category('pxr-shirts', 'Shirts');
        $this->category('pxr-shoes', 'Shoes');
        $this->assign('pxr-alpha', 'pxr-shirts', 10);
        $this->assign('pxr-alpha', 'pxr-shoes', 20);
        $this->assign('pxr-beta', 'pxr-shoes', 5);
    }

    private function plantOrders(): void
    {
        $this->order('pxr-order-1', '2031-06-01 10:00:00');
        $this->line('pxr-order-1', 'pxr-alpha-s', 'ALPHA-S', 'Alpha S', 2, 20.0);
        $this->line('pxr-order-1', 'pxr-beta', 'BETA-0', 'Beta', 1, 30.0);
        $this->order('pxr-order-2', '2031-06-02 09:00:00');
        $this->line('pxr-order-2', 'pxr-alpha-m', 'ALPHA-M', 'Alpha M', 1, 10.0);
        $this->line('pxr-order-2', 'pxr-gamma', 'GAMMA-0', 'Gamma', 5, 5.0);
        $this->line('pxr-order-2', 'pxr-gone', 'GONE-1', 'Gone', 1, 1.0);
        $this->line('pxr-order-2', 'pxr-beta', 'BETA-0', 'Beta', 9, 90.0, cancelled: true);
        $this->order('pxr-order-3', '2031-06-02 12:00:00', cancelled: true);
        $this->line('pxr-order-3', 'pxr-gamma', 'GAMMA-0', 'Gamma', 100, 999.0);
        $this->order('pxr-order-4', '2031-06-01 12:00:00', shopId: 2);
        $this->line('pxr-order-4', 'pxr-gamma', 'GAMMA-0', 'Gamma', 100, 999.0);
        $this->order('pxr-order-5', '2031-06-04 00:00:00');
        $this->line('pxr-order-5', 'pxr-gamma', 'GAMMA-0', 'Gamma', 100, 999.0);
    }

    private function product(string $id, string $parentId, string $number, string $title): void
    {
        $this->connection->executeStatement(
            'INSERT INTO oxarticles (OXID, OXSHOPID, OXPARENTID, OXARTNUM, OXTITLE, OXTITLE_1)'
            . ' VALUES (?, 1, ?, ?, ?, ?)',
            [$id, $parentId, $number, $title, $title . ' (en)']
        );
        // the shop reads through views that join the shop map, as a saved product always has
        $this->connection->executeStatement(
            'INSERT INTO oxarticles2shop (OXSHOPID, OXMAPOBJECTID) SELECT 1, OXMAPID FROM oxarticles WHERE OXID = ?',
            [$id]
        );
    }

    private function category(string $id, string $title): void
    {
        $this->connection->executeStatement(
            'INSERT INTO oxcategories (OXID, OXPARENTID, OXROOTID, OXSHOPID, OXTITLE, OXTITLE_1)'
            . " VALUES (?, 'oxrootid', ?, 1, ?, ?)",
            [$id, $id, $title, $title . ' (en)']
        );
        $this->connection->executeStatement(
            'INSERT INTO oxcategories2shop (OXSHOPID, OXMAPOBJECTID)'
            . ' SELECT 1, OXMAPID FROM oxcategories WHERE OXID = ?',
            [$id]
        );
    }

    private function assign(string $productId, string $categoryId, int $time): void
    {
        $this->connection->executeStatement(
            'INSERT INTO oxobject2category (OXID, OXSHOPID, OXOBJECTID, OXCATNID, OXTIME) VALUES (?, 1, ?, ?, ?)',
            [$productId . '-' . $categoryId, $productId, $categoryId, $time]
        );
    }

    private function order(string $id, string $date, bool $cancelled = false, int $shopId = 1): void
    {
        $this->connection->executeStatement(
            'INSERT INTO oxorder (OXID, OXSHOPID, OXUSERID, OXORDERDATE, OXORDERNR, OXTOTALORDERSUM, OXTOTALBRUTSUM,'
            . ' OXCURRENCY, OXCURRATE, OXSTORNO, OXPAYMENTTYPE) VALUES (?, ?, ?, ?, 0, 10, 10, ?, 1, ?, ?)',
            [$id, $shopId, 'oxdefaultadmin', $date, 'EUR', $cancelled ? 1 : 0, 'oxidinvoice']
        );
    }

    private function line(
        string $orderId,
        string $productId,
        string $number,
        string $title,
        int $amount,
        float $gross,
        bool $cancelled = false
    ): void {
        $this->connection->executeStatement(
            'INSERT INTO oxorderarticles (OXID, OXORDERID, OXAMOUNT, OXARTID, OXARTNUM, OXTITLE, OXBRUTPRICE, OXSTORNO)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $orderId . '-' . $productId . ($cancelled ? '-x' : ''),
                $orderId, $amount, $productId, $number, $title, $gross, $cancelled ? 1 : 0,
            ]
        );
    }
}
