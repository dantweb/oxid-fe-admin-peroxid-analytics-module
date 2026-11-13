<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Unit\Assistant;

use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsBucket;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsGranularity;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsOrderCounts;
use Dantweb\PeroxidAnalytics\Analytics\DataType\AdminAnalyticsSeries;
use Dantweb\PeroxidAnalytics\Analytics\Service\AnalyticsReaderInterface;
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

    public function testManifestDeclaresOrderCountsAndSeries(): void
    {
        $this->assertSame(['analytics.order_counts', 'analytics.series'], array_keys($this->capabilities));
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

    public function testManifestSourcesAreMethodsOfThePublicReader(): void
    {
        foreach ($this->capabilities as $name => $capability) {
            $this->assertSame(AnalyticsReaderInterface::class, $capability['source']['service'], $name);
            $method = new ReflectionMethod(AnalyticsReaderInterface::class, $capability['source']['method']);
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
