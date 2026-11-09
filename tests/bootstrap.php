<?php

/**
 * Loads the OXID shop (oxNew(), Registry, the unified namespace) and makes the test namespaces of this module, of
 * graphql-base and of the admin modules resolvable: autoload-dev of a path-installed package is not part of the
 * shop's autoloader.
 *
 * Run from the SDK root (inside the php container):
 *   php vendor/bin/phpunit -c extensions/oxid-fe-admin-peroxid-analytics/tests/phpunit.xml --testsuite Unit
 */

declare(strict_types=1);

$shopBootstrap = (static function (): string {
    $candidates = [
        '/var/www/source/bootstrap.php',
        dirname(__DIR__, 4) . '/source/bootstrap.php',
    ];
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }
    throw new RuntimeException('OXID shop bootstrap not found; run the tests inside the SDK php container.');
})();

require_once $shopBootstrap;

$testNamespaceDirectories = [
    'Dantweb\\PeroxidAnalytics\\Tests\\' => __DIR__,
    'OxidEsales\\GraphQL\\Admin\\Tests\\' => VENDOR_PATH . 'oxid-esales/graphql-admin/tests',
    'OxidEsales\\GraphQL\\AdminApi\\Tests\\' => VENDOR_PATH . 'oxid-esales/graphql-admin-api/tests',
    'OxidEsales\\GraphQL\\Base\\Tests\\' => VENDOR_PATH . 'oxid-esales/graphql-base/tests',
    'OxidEsales\\SealedChannel\\Tests\\' => VENDOR_PATH . 'oxid-esales/sealed-channel/tests',
];

spl_autoload_register(static function (string $class) use ($testNamespaceDirectories): void {
    foreach ($testNamespaceDirectories as $prefix => $directory) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            continue;
        }
        $file = $directory . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});

/**
 * The integration test drives the query with a real admin (a temporary user, his roles, a session, a token). The
 * admin surface is oxid-admin-graphql-api's, so the base class is that module's test case when its tests are on disk
 * (an SDK checkout), and a stand-in that skips the test otherwise.
 */
spl_autoload_register(static function (string $class): void {
    $base = 'Dantweb\\PeroxidAnalytics\\Tests\\Support\\AdminSurfaceTestCase';
    if ($class !== $base) {
        return;
    }
    $surface = 'OxidEsales\\GraphQL\\AdminApi\\Tests\\Integration\\AdminSession\\AdminSessionTestCase';
    $skipped = 'Dantweb\\PeroxidAnalytics\\Tests\\Support\\SkippedAdminSurfaceTestCase';
    class_alias(class_exists($surface) ? $surface : $skipped, $base);
});
