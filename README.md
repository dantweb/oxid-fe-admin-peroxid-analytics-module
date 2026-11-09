# oxid-fe-admin-peroxid-analytics (OXID module `peroxid_analytics`)

The backend of the [Analytics plugin of oxid-fe-admin](https://github.com/dantweb/oxid-fe-admin-peroxid-analytics): an
OXID eShop module on **`graphql-base`** that adds one admin GraphQL query, `adminAnalyticsSeries`, to the admin API of
[`graphql-admin`](../oxid-admin-graphql) (`oe_graphql_admin`). It answers the orders, gross sales and new customers of the
shop **per day or ISO week**, already aggregated: no order, no customer and no personal datum leaves the shop. With it, the
plugin's page shows the shop's numbers instead of its demo data.

| | |
| --- | --- |
| Module id | `peroxid_analytics` (`src/Module.php`) |
| Composer | `dantweb/oxid-fe-admin-peroxid-analytics-module`, type `oxideshop-module` |
| Depends on | `oxid-esales/graphql-base` ^12 (the GraphQL framework), `oxid-esales/graphql-admin` (admin session, right check, rights catalogue, `ShopScope`) |
| Adds | query `adminAnalyticsSeries`, types `AdminAnalyticsSeries`, `AdminAnalyticsBucket`, enum `AdminAnalyticsGranularity`, right `ADMIN_ANALYTICS_VIEW` |
| Extends | nothing of the shop: no class extension, no controller, no setting, no table |
| Licence | MIT |

## The query

```graphql
query ($from: DateTime!, $to: DateTime!, $granularity: AdminAnalyticsGranularity!) {
  adminAnalyticsSeries(from: $from, to: $to, granularity: $granularity) {
    from to granularity currency
    buckets { key orders sales customers ordersPerCustomer salesPerCustomer }
    totals  { key orders sales customers ordersPerCustomer salesPerCustomer }
  }
}
```

- `from`, `to`: whole days, both included (the time of day is dropped); at most 366 days; `to` not before `from`
  (else a GraphQL error with `extensions.errorCode = analytics_range_invalid`).
- `granularity`: `DAILY` (one bucket per calendar day) or `WEEKLY` (one per ISO week, Monday to Sunday, keyed by its Monday).
- Every bucket of the period is in the answer, in order, empty ones as zeros, so a client draws a complete curve. `totals`
  (`key: "total"`) sums the period; the two ratios are the ratios of the sums, 0 when there are no customers.
- `orders` and `sales`: `oxorder` rows of the shop of the request with `OXSTORNO = 0`, `sales = SUM(OXTOTALORDERSUM)`
  (gross, in the shop's currency, `currency` names it). `customers`: `oxuser` rows of the shop with `OXRIGHTS = 'user'`
  by `OXCREATE` (admins are not customers).
- Needs an admin session and the right **`ADMIN_ANALYTICS_VIEW`** (`#[Right]`, no `#[Logged]`: a refusal carries
  `admin_session_required` or `permission_denied`, as everywhere in the admin API).

Full reference: [`docs/api/analytics.md`](docs/api/analytics.md). How it is built: [`docs/architecture.md`](docs/architecture.md).

## Install (shop, from the shop root)

```bash
composer config repositories.dantweb/oxid-fe-admin-peroxid-analytics-module \
  --json '{"type":"path","url":"./extensions/oxid-fe-admin-peroxid-analytics","options":{"symlink":true}}'
composer require "dantweb/oxid-fe-admin-peroxid-analytics-module:*@dev"
bin/oe-console oe:module:install extensions/oxid-fe-admin-peroxid-analytics
bin/oe-console oe:module:activate peroxid_analytics        # after oe_graphql_base and oe_graphql_admin
bin/oe-console oe:graphql:cache-clear
rm -f var/cache/container/container_cache_shop_1.php
```

GraphQLite caches the schema on disk and the shop its compiled container: the last two lines are needed after every
activation or change of `services.yaml`. Check: the schema's `Query` type lists `adminAnalyticsSeries`.

The right joins the catalogue of `oe_graphql_admin` (`RightsProviderInterface`, tag `graphql_admin_rights_provider`); the
superadmin role holds it, other roles get it with `adminRoleModify`. The admin SPA's generated catalogue does not need it:
plugin rights are strings there.

## Quality gate

```bash
# from the SDK root, inside the php container
docker compose exec -w /var/www/extensions/oxid-fe-admin-peroxid-analytics php composer test-unit
docker compose exec -w /var/www/extensions/oxid-fe-admin-peroxid-analytics php composer test-integration
docker compose exec -w /var/www/extensions/oxid-fe-admin-peroxid-analytics php composer phpstan      # level 8
# the static tools install without the shop
composer install -d tests/tools && composer phpcs && composer phpmd
```

- **Unit** (`tests/Unit`): the bucket calendar (days, ISO Mondays, complete key lists), the period rules, the reader over a
  fake repository (empty buckets, weekly folding, totals, ratios), the right, the namespace mapper, and a wiring test that
  holds `metadata.php`, `services.yaml` and `composer.json` to the code.
- **Integration** (`tests/Integration`): the whole stack with a real admin through oxid-admin-graphql-api's test surface:
  no token → `admin_session_required`; another right → `permission_denied`; the right → the buckets of planted orders and
  a customer (a cancelled order excluded), weekly, and the refusal of a reversed period. The base class is that module's
  `AdminSessionTestCase` when its tests are on disk (an SDK checkout), a stand-in that skips otherwise.
- **Proof on real data:** `AnalyticsReaderInterface::series()` for October 2026 on the SDK shop answers exactly what two
  plain SQL sums answer (687 orders, 252,986.09 EUR, 711 customers); the weekly buckets sum to the same.

CI (`.github/workflows/ci.yml`): `static` (phpcs, phpmd, composer audit of the tools) on every push; `shop` builds the
SDK, installs graphql-base and this module, runs PHPStan and the unit tests, and the integration tests when the private
admin modules can be checked out (deploy keys in secrets, see the workflow).

> **Local integration tests.** In an SDK shop where the Stripe and PayPal modules are active with an unreadable
> configuration, *every* admin integration suite (this module's and `oxid-admin-graphql`'s own) fails while graphqlite
> builds the schema inside the test container (`ModuleConfigurationUnreadableException` of payment-base). That is the
> environment, not this module: deactivate those modules for the run, or run the suite in CI's clean shop.
