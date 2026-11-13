<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Integration;

use DateTimeImmutable;
use Dantweb\PeroxidAnalytics\Analytics\Service\AnalyticsReaderInterface;
use Doctrine\DBAL\Connection;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Database\ConnectionProviderInterface;
use PHPUnit\Framework\TestCase;

/**
 * The reader and repository on the real database (no HTTP surface): order counts of a period, cancelled orders counted
 * separately, other days and other shops not counted. Rolled back.
 */
final class OrderCountsRepositoryTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $container = ContainerFactory::getInstance()->getContainer();
        $this->connection = $container->get(ConnectionProviderInterface::class)->get();
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
    }

    public function testOrderCountsOnlyTheRangePaidShippedCancelledSeparately(): void
    {
        $this->plant('pxa-count-1', '2031-04-01 10:00:00', paid: true, sent: true);
        $this->plant('pxa-count-2', '2031-04-02 23:59:00');
        $this->plant('pxa-count-3', '2031-04-02 12:00:00', cancelled: true);
        $this->plant('pxa-count-4', '2031-04-03 00:00:00');
        $this->plant('pxa-count-5', '2031-04-01 09:00:00', shopId: 2);

        $counts = $this->reader()->orderCounts(
            new DateTimeImmutable('2031-04-01'),
            new DateTimeImmutable('2031-04-02')
        );

        $this->assertSame([3, 1, 1, 1], [$counts->total(), $counts->paid(), $counts->shipped(), $counts->cancelled()]);
    }

    private function reader(): AnalyticsReaderInterface
    {
        return ContainerFactory::getInstance()->getContainer()->get(AnalyticsReaderInterface::class);
    }

    private function plant(
        string $id,
        string $date,
        bool $paid = false,
        bool $sent = false,
        bool $cancelled = false,
        int $shopId = 1
    ): void {
        $never = '0000-00-00 00:00:00';
        $this->connection->executeStatement(
            'INSERT INTO oxorder (OXID, OXSHOPID, OXUSERID, OXORDERDATE, OXORDERNR, OXTOTALORDERSUM, OXTOTALBRUTSUM,'
            . ' OXCURRENCY, OXCURRATE, OXSTORNO, OXPAYMENTTYPE, OXPAID, OXSENDDATE)'
            . ' VALUES (?, ?, ?, ?, 0, 10, 10, ?, 1, ?, ?, ?, ?)',
            [
                $id, $shopId, 'oxdefaultadmin', $date, 'EUR', $cancelled ? 1 : 0, 'oxidinvoice',
                $paid ? $date : $never, $sent ? $date : $never,
            ]
        );
    }
}
