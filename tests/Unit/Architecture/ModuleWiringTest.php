<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Unit\Architecture;

use Dantweb\PeroxidAnalytics\Module;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * metadata.php, services.yaml and composer.json agree with the code: the id and the version, the two tags graphql-base
 * and graphql-admin collect, the aliases of the service interfaces, and the value objects kept out of the container.
 */
final class ModuleWiringTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function testMetadataCarriesTheModuleIdAndVersion(): void
    {
        $aModule = [];
        require self::ROOT . '/metadata.php';

        $this->assertSame(Module::MODULE_ID, $aModule['id']);
        $this->assertSame(Module::VERSION, $aModule['version']);
        $this->assertSame([], $aModule['extend'], 'the module extends nothing of the shop');
        $this->assertSame([], $aModule['settings'], 'the module has no settings');
    }

    public function testServicesDeclareTheTwoTagsTheAliasesAndKeepValueObjectsOut(): void
    {
        $services = Yaml::parseFile(self::ROOT . '/services.yaml')['services'];

        $this->assertSame(
            ['graphql_namespace_mapper'],
            $services['Dantweb\\PeroxidAnalytics\\Shared\\Service\\NamespaceMapper']['tags']
        );
        $this->assertSame(
            ['graphql_admin_rights_provider'],
            $services['Dantweb\\PeroxidAnalytics\\Authorization\\AnalyticsRightsProvider']['tags']
        );
        foreach (
            [
                'Dantweb\\PeroxidAnalytics\\Analytics\\Service\\AnalyticsReaderInterface',
                'Dantweb\\PeroxidAnalytics\\Analytics\\Infrastructure\\AnalyticsRepositoryInterface',
                'Dantweb\\PeroxidAnalytics\\Analytics\\Infrastructure\\ShopCurrencyInterface',
            ] as $interface
        ) {
            $this->assertTrue($services[$interface]['public'], $interface);
            $this->assertTrue(interface_exists($interface), $interface);
            $this->assertTrue(class_exists($services[$interface]['alias']), $services[$interface]['alias']);
        }
        $exclude = $services['Dantweb\\PeroxidAnalytics\\']['exclude'];
        $this->assertContains('src/**/{DataType,Exception}', $exclude);
        $this->assertContains('src/Analytics/Infrastructure/DailyFigures.php', $exclude);
    }

    public function testComposerRequiresGraphqlBaseAndTheAdminApi(): void
    {
        $composer = json_decode((string) file_get_contents(self::ROOT . '/composer.json'), true);

        $this->assertSame('oxideshop-module', $composer['type']);
        $this->assertArrayHasKey('oxid-esales/graphql-base', $composer['require']);
        $this->assertArrayHasKey('oxid-esales/graphql-admin', $composer['require']);
        $this->assertSame(['Dantweb\\PeroxidAnalytics\\' => './src'], $composer['autoload']['psr-4']);
    }
}
