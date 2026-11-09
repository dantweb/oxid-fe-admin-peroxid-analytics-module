<?php

declare(strict_types=1);

namespace Dantweb\PeroxidAnalytics\Authorization;

use OxidEsales\GraphQL\Admin\Authorization\DataType\RightDefinition;
use OxidEsales\GraphQL\Admin\Authorization\Service\RightsProviderInterface;

/**
 * The one right of this module, added to the rights catalogue of graphql-admin (tag `graphql_admin_rights_provider`).
 * The superadmin role holds it; other roles get it through adminRoleCreate / adminRoleModify. The admin SPA's
 * analytics plugin asks for it before it switches from its demo data to the shop's numbers.
 */
final class AnalyticsRightsProvider implements RightsProviderInterface
{
    public const VIEW = 'ADMIN_ANALYTICS_VIEW';

    private const DOMAIN = 'analytics';

    public function rights(): array
    {
        return [
            RightDefinition::of(
                self::VIEW,
                self::DOMAIN,
                'Read the sales and customer analytics (aggregated numbers per day or week, no personal data)'
            ),
        ];
    }
}
