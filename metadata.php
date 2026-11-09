<?php

/**
 * PEROXID analytics for the admin GraphQL API. MIT licence, see LICENSE.
 */

declare(strict_types=1);

use Dantweb\PeroxidAnalytics\Module;

/**
 * Metadata version
 */
$sMetadataVersion = '2.1';

/**
 * Module information
 */
$aModule = [
    'id' => Module::MODULE_ID,
    'title' => [
        'de' => 'PEROXID Analytics (GraphQL Admin)',
        'en' => 'PEROXID Analytics (GraphQL Admin)',
    ],
    'description' => [
        'de' => 'Admin-GraphQL-Analytics: Bestellungen, Umsatz und Neukunden je Tag oder ISO-Woche, '
            . 'für das Analytics-Plugin von oxid-fe-admin. Nur Summen, keine personenbezogenen Daten.',
        'en' => 'Admin GraphQL analytics: orders, sales and new customers per day or ISO week, '
            . 'for the Analytics plugin of oxid-fe-admin. Aggregates only, no personal data.',
    ],
    'thumbnail' => '',
    'version' => Module::VERSION,
    'author' => 'Daniil Tkachev',
    'url' => 'https://github.com/dantweb/oxid-fe-admin-peroxid-analytics',
    'email' => 'daniil.tkachev@oxid-esales.com',
    // nothing of the shop is extended: the module only adds GraphQL operations (NamespaceMapper) and one right
    'extend' => [],
    'controllers' => [],
    'settings' => [],
];
