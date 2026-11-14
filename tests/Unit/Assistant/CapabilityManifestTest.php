<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Unit\Assistant;

use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsBucket;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsGranularity;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsOrderCounts;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRankItem;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRankMeasure;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsRanking;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsSeries;
use Dantweb\PeroxidAnalytics\Analytics\Service\AnalyticsReaderInterface;
use Dantweb\PeroxidAnalytics\Analytics\Service\RankingReader;
use Dantweb\PeroxidAnalytics\Analytics\Service\RankingReaderInterface;
use Dantweb\PeroxidAnalytics\Authorization\AnalyticsRightsProvider;
use Dantweb\PeroxidAnalytics\Module;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Yaml\Yaml;

/**
 * The module declares its read capabilities for the admin assistant in assistant.capabilities.yaml (no PHP, no
 * dependency on the assistant). These tests tie the file to the module's real code: a rename here breaks a test, not
 * the shop's assistant.
 */
final class CapabilityManifestTest extends TestCase
{
    /** @var array<string, array<string, mixed>> */
    private array $capabilities;

    protected function setUp(): void
    {
        $manifest = Yaml::parseFile(dirname(__DIR__, 3) . '/assistant.capabilities.yaml');
        $this->capabilities = $manifest['capabilities'];
    }

    public function testManifestDeclaresOrderCountsSeriesAndTheTwoRankings(): void
    {
        $this->assertSame(
            ['analytics.order_counts', 'analytics.series', 'analytics.top_products', 'analytics.top_categories'],
            array_keys($this->capabilities)
        );
    }

    public function testManifestRightIsTheModulesViewRight(): void
    {
        foreach ($this->capabilities as $name => $capability) {
            $this->assertSame(AnalyticsRightsProvider::VIEW, $capability['right'], $name);
        }
    }

    public function testManifestModuleIdEqualsModuleConstant(): void
    {
        foreach ($this->capabilities as $name => $capability) {
            $this->assertSame(Module::MODULE_ID, $capability['module'], $name);
        }
    }

    public function testManifestGranularityValuesEqualEnumCases(): void
    {
        $cases = array_map(
            static fn (AdminAnalyticsGranularity $case): string => $case->value,
            AdminAnalyticsGranularity::cases()
        );

        $this->assertSame($cases, $this->capabilities['analytics.series']['input']['granularity']['values']);
    }

    public function testManifestMeasureValuesEqualEnumCases(): void
    {
        $cases = array_map(
            static fn (AdminAnalyticsRankMeasure $case): string => $case->value,
            AdminAnalyticsRankMeasure::cases()
        );

        foreach (['analytics.top_products', 'analytics.top_categories'] as $name) {
            $this->assertSame($cases, $this->capabilities[$name]['input']['measure']['values'], $name);
        }
    }

    public function testManifestLimitIsAnIntegerWithinTheReadersBounds(): void
    {
        foreach (['analytics.top_products', 'analytics.top_categories'] as $name) {
            $limit = $this->capabilities[$name]['input']['limit'];
            $this->assertSame('integer', $limit['type'], $name);
            $this->assertStringContainsString(
                RankingReader::MIN_LIMIT . ' to ' . RankingReader::MAX_LIMIT,
                $limit['description'],
                $name
            );
        }
    }

    public function testManifestSourcesAreMethodsOfThePublicReaders(): void
    {
        $readers = [
            'analytics.order_counts' => AnalyticsReaderInterface::class,
            'analytics.series' => AnalyticsReaderInterface::class,
            'analytics.top_products' => RankingReaderInterface::class,
            'analytics.top_categories' => RankingReaderInterface::class,
        ];
        foreach ($this->capabilities as $name => $capability) {
            $this->assertSame($readers[$name], $capability['source']['service'], $name);
            $method = new ReflectionMethod($readers[$name], $capability['source']['method']);
            $parameters = array_map(static fn ($parameter): string => $parameter->getName(), $method->getParameters());
            $this->assertSame(array_keys($capability['input']), $parameters, $name . ': input = parameters');
        }
    }

    public function testManifestResultPathsExistOnTheTypes(): void
    {
        $counts = $this->capabilities['analytics.order_counts']['result'];
        $this->assertPaths($counts['summary'], AdminAnalyticsOrderCounts::class);
        $series = $this->capabilities['analytics.series']['result'];
        $this->assertPaths($series['summary'], AdminAnalyticsSeries::class);
        $this->assertSame('buckets', $series['facts']['path']);
        $this->assertTrue(method_exists(AdminAnalyticsSeries::class, 'buckets'));
        $this->assertPaths($series['facts']['label'] . ' ' . $series['facts']['value'], AdminAnalyticsBucket::class);
        foreach (['analytics.top_products', 'analytics.top_categories'] as $name) {
            $ranking = $this->capabilities[$name]['result'];
            $this->assertPaths($ranking['summary'], AdminAnalyticsRanking::class);
            $this->assertSame('items', $ranking['facts']['path'], $name);
            $this->assertTrue(method_exists(AdminAnalyticsRanking::class, 'items'));
            $facts = $ranking['facts'];
            $this->assertPaths($facts['label'] . ' ' . $facts['value'], AdminAnalyticsRankItem::class);
        }
    }

    /** @param class-string $class */
    private function assertPaths(string $template, string $class): void
    {
        preg_match_all('/\{\{\s*([a-zA-Z_.]+)\s*\}\}/', $template, $paths);
        $this->assertNotEmpty($paths[1]);
        foreach ($paths[1] as $path) {
            $type = $class;
            foreach (explode('.', $path) as $step) {
                $this->assertTrue(method_exists($type, $step), $type . '::' . $step . ' for {{ ' . $path . ' }}');
                $return = (new ReflectionMethod($type, $step))->getReturnType();
                $type = $return instanceof \ReflectionNamedType && !$return->isBuiltin() ? $return->getName() : $type;
            }
        }
    }
}
