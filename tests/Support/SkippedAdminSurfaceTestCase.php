<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * Stands in for oxid-admin-graphql-api's AdminSessionTestCase when that module's tests are not on disk: the
 * integration tests that need a real admin session are skipped, not failed.
 */
abstract class SkippedAdminSurfaceTestCase extends TestCase
{
    protected function setUp(): void
    {
        $this->markTestSkipped('oxid-admin-graphql-api tests are not installed next to this module: no admin surface');
    }
}
