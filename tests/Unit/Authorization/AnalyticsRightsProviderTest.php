<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Tests\Unit\Authorization;

use Dantweb\PeroxidAnalytics\Authorization\AnalyticsRightsProvider;
use OxidEsales\GraphQL\Admin\Authorization\Rights;
use OxidEsales\GraphQL\Admin\Authorization\Service\RightsProviderInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AnalyticsRightsProviderTest extends TestCase
{
    public function testAddsExactlyTheViewRightInTheAnalyticsDomain(): void
    {
        $provider = new AnalyticsRightsProvider();

        $this->assertInstanceOf(RightsProviderInterface::class, $provider);
        $rights = $provider->rights();
        $this->assertCount(1, $rights);
        $this->assertSame('ADMIN_ANALYTICS_VIEW', $rights[0]->right());
        $this->assertSame('analytics', $rights[0]->domain());
        $this->assertStringContainsString('no personal data', $rights[0]->description());
    }

    public function testTheRightNameIsNotOneOfTheCoreCatalogue(): void
    {
        $core = (new ReflectionClass(Rights::class))->getConstants();

        $this->assertNotContains(AnalyticsRightsProvider::VIEW, $core);
    }
}
