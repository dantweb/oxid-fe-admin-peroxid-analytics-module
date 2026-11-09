<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Unit\Shared;

use Dantweb\PeroxidAnalytics\Shared\Service\NamespaceMapper;
use OxidEsales\GraphQL\Base\Framework\NamespaceMapperInterface;
use PHPUnit\Framework\TestCase;

final class NamespaceMapperTest extends TestCase
{
    public function testMapsTheControllerAndTypeFoldersThatExist(): void
    {
        $mapper = new NamespaceMapper();

        $this->assertInstanceOf(NamespaceMapperInterface::class, $mapper);
        foreach ([$mapper->getControllerNamespaceMapping(), $mapper->getTypeNamespaceMapping()] as $mapping) {
            $this->assertCount(1, $mapping);
            foreach ($mapping as $namespace => $directory) {
                $this->assertStringStartsWith('Dantweb\\PeroxidAnalytics\\Analytics\\', $namespace);
                $this->assertDirectoryExists($directory);
            }
        }
        $controllers = $mapper->getControllerNamespaceMapping();
        $this->assertArrayHasKey('Dantweb\\PeroxidAnalytics\\Analytics\\Controller', $controllers);
        $this->assertArrayHasKey('Dantweb\\PeroxidAnalytics\\Analytics\\DataType', $mapper->getTypeNamespaceMapping());
    }
}
