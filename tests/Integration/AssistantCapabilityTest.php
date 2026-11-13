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

    /** @param array<string, string> $payload */
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
}
