<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Shared\Service;

use OxidEsales\GraphQL\Base\Framework\NamespaceMapperInterface;

/**
 * Tells graphql-base where this module's GraphQL controllers and types live (tag `graphql_namespace_mapper`).
 * One domain folder: src/Analytics/{Controller,DataType}.
 */
final class NamespaceMapper implements NamespaceMapperInterface
{
    private const ROOT_NAMESPACE = 'Dantweb\\PeroxidAnalytics\\Analytics\\';

    public function getControllerNamespaceMapping(): array
    {
        return [self::ROOT_NAMESPACE . 'Controller' => $this->folder('Controller')];
    }

    public function getTypeNamespaceMapping(): array
    {
        return [self::ROOT_NAMESPACE . 'DataType' => $this->folder('DataType')];
    }

    private function folder(string $name): string
    {
        return __DIR__ . '/../../Analytics/' . $name . '/';
    }
}
