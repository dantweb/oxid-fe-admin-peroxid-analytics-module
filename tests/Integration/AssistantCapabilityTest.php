<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Integration;

use Doctrine\DBAL\Connection;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Database\ConnectionProviderInterface;
use PHPUnit\Framework\TestCase;

/**
 * Both modules active: the admin assistant offers this module's manifest capabilities and answers through its policy
 * gateway. Skipped where the assistant is not installed, so this module's own CI stays independent of it.
 */
final class AssistantCapabilityTest extends TestCase
{
    private const REGISTRY = 'OxidEsales\\AdminAssistant\\Core\\Application\\Capability\\CapabilityRegistry';
    private const PREPARER = 'OxidEsales\\AdminAssistant\\Core\\Application\\Execution\\ProposalPreparerInterface';
    private const PROPOSAL = 'OxidEsales\\AdminAssistant\\Core\\Application\\Planning\\ActionProposal';
    private const PRINCIPAL = 'OxidEsales\\AdminAssistant\\Core\\Application\\Principal\\Principal';

    private Connection $connection;

    protected function setUp(): void
    {
        if (!class_exists(self::REGISTRY)) {
            $this->markTestSkipped('oe_admin_assistant is not installed');
        }
        $container = ContainerFactory::getInstance()->getContainer();
        $this->connection = $container->get(ConnectionProviderInterface::class)->get();
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->rollBack();
        }
    }

    public function testRegistryModuleActiveOffersBothCapabilities(): void
    {
        $registry = ContainerFactory::getInstance()->getContainer()->get(self::REGISTRY);

        $this->assertNotNull($registry->find('analytics.order_counts'));
        $this->assertNotNull($registry->find('analytics.series'));
        $this->assertNotNull($registry->find('analytics.top_products'));
        $this->assertNotNull($registry->find('analytics.top_categories'));
    }

    public function testTopProductsThroughTheAssistantGatewayStatesTheShareOfTheTop(): void
    {
        $this->plant('pxa-assist-4', '2031-05-13 10:00:00');
        $this->plantLine('pxa-assist-4', 'pxa-assist-hat', 'Assist Hat', 25.0);

        $summary = $this->read(
            'analytics.top_products',
            ['from' => '2031-05-13', 'to' => '2031-05-13', 'limit' => 5, 'measure' => 'SALES']
        );

        $this->assertStringStartsWith('The top products hold 100 % of the total 25', $summary);
    }

    public function testTopCategoriesThroughTheAssistantGatewayNamesTheCategorylessSales(): void
    {
        $this->plant('pxa-assist-5', '2031-05-14 10:00:00');
        $this->plantLine('pxa-assist-5', 'pxa-assist-cap', 'Assist Cap', 8.0);

        $summary = $this->read(
            'analytics.top_categories',
            ['from' => '2031-05-14', 'to' => '2031-05-14', 'limit' => 3, 'measure' => 'UNITS']
        );

        $this->assertStringStartsWith('The top categories hold 100 % of the total 1', $summary);
    }

    public function testOrderCountsThroughTheAssistantGatewaySummaryHasTotalAndSplit(): void
    {
        $this->plant('pxa-assist-1', '2031-05-10 10:00:00', cancelled: false);
        $this->plant('pxa-assist-2', '2031-05-10 18:00:00', cancelled: true);

        $summary = $this->read('analytics.order_counts', ['from' => '2031-05-10', 'to' => '2031-05-10']);

        $this->assertSame('2 orders from 2031-05-10 to 2031-05-10 (paid 0, shipped 0, cancelled 1)', $summary);
    }

    public function testSeriesThroughTheAssistantGatewayTotalsAndOneFactPerDay(): void
    {
        $this->plant('pxa-assist-3', '2031-05-11 10:00:00');

        $summary = $this->read(
            'analytics.series',
            ['from' => '2031-05-10', 'to' => '2031-05-12', 'granularity' => 'DAILY']
        );

        $this->assertStringStartsWith('1 orders, 10', $summary);
        $this->assertStringContainsString('(2031-05-10 – 2031-05-12)', $summary);
    }

    public function testTopProductsThroughTheGatewayCarryTheProductIdAsAnEntityLink(): void
    {
        $this->plant('pxa-assist-6', '2031-05-15 10:00:00');
        $this->plantLine('pxa-assist-6', 'pxa-assist-link', 'Linked Hat', 12.0);

        $facts = $this->readFacts(
            'analytics.top_products',
            ['from' => '2031-05-15', 'to' => '2031-05-15', 'limit' => 3, 'measure' => 'SALES']
        );

        $this->assertSame('product', $facts[0]->entityType);
        $this->assertSame('pxa-assist-link', $facts[0]->entityId);
        $this->assertStringContainsString('Linked Hat', $facts[0]->label);
    }

    public function testTopCategoriesThroughTheGatewayCarryNoEntityLink(): void
    {
        $this->plant('pxa-assist-7', '2031-05-16 10:00:00');
        $this->plantLine('pxa-assist-7', 'pxa-assist-nolink', 'No Link Cap', 4.0);

        $facts = $this->readFacts(
            'analytics.top_categories',
            ['from' => '2031-05-16', 'to' => '2031-05-16', 'limit' => 3, 'measure' => 'SALES']
        );

        $this->assertNotEmpty($facts);
        $this->assertNull($facts[0]->entityType);
        $this->assertNull($facts[0]->entityId);
    }

    /**
     * @param array<string, string|int> $payload
     * @return list<object{label: string, entityType: ?string, entityId: ?string}>
     */
    private function readFacts(string $capability, array $payload): array
    {
        $proposalClass = self::PROPOSAL;
        $principalClass = self::PRINCIPAL;
        $prepared = ContainerFactory::getInstance()->getContainer()->get(self::PREPARER)->prepare(
            new $proposalClass($capability, $payload, 'x'),
            $principalClass::admin('oxdefaultadmin', 1)
        );

        return $prepared->result?->facts ?? [];
    }

    /** @param array<string, string|int> $payload */
    private function read(string $capability, array $payload): string
    {
        $proposalClass = self::PROPOSAL;
        $principalClass = self::PRINCIPAL;
        $prepared = ContainerFactory::getInstance()->getContainer()->get(self::PREPARER)->prepare(
            new $proposalClass($capability, $payload, 'x'),
            $principalClass::admin('oxdefaultadmin', 1)
        );

        return (string) $prepared->result?->summary;
    }

    private function plant(string $id, string $date, bool $cancelled = false): void
    {
        $never = '0000-00-00 00:00:00';
        $this->connection->executeStatement(
            'INSERT INTO oxorder (OXID, OXSHOPID, OXUSERID, OXORDERDATE, OXORDERNR, OXTOTALORDERSUM, OXTOTALBRUTSUM,'
            . ' OXCURRENCY, OXCURRATE, OXSTORNO, OXPAYMENTTYPE, OXPAID, OXSENDDATE)'
            . ' VALUES (?, 1, ?, ?, 0, 10, 10, ?, 1, ?, ?, ?, ?)',
            [$id, 'oxdefaultadmin', $date, 'EUR', $cancelled ? 1 : 0, 'oxidinvoice', $never, $never]
        );
    }

    private function plantLine(string $orderId, string $productId, string $title, float $gross): void
    {
        $this->connection->executeStatement(
            'INSERT INTO oxorderarticles (OXID, OXORDERID, OXAMOUNT, OXARTID, OXARTNUM, OXTITLE, OXBRUTPRICE)'
            . ' VALUES (?, ?, 1, ?, ?, ?, ?)',
            [$orderId . $productId, $orderId, $productId, strtoupper($productId), $title, $gross]
        );
    }
}
