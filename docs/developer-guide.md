# PEROXID Analytics module: developer guide

This document is written in ASD-STE100 Simplified Technical English. It tells you how the module is built, how to
extend it and how to test it.

## 1 Structure

The module has a layer for each task. The code is in `src/`.

| Folder or class | Task | Is it a service? |
|---|---|---|
| `Analytics/Controller/AnalyticsQueries` | Maps the five queries. It has `#[Query]` and `#[Right]`. It has no rules. | Yes (public) |
| `Analytics/Service/AnalyticsReader` | Lays the calendar over the per-day figures. Makes the totals and the ratios. | Yes (alias `AnalyticsReaderInterface`) |
| `Analytics/Service/RankingReader` | Checks the list length and the period. Asks the repository. | Yes (alias `RankingReaderInterface`) |
| `Analytics/Service/RankingBuilder` | Makes `others` and the shares of a ranking. | Yes |
| `Analytics/Service/ShareSeriesBuilder` | Lays the per-day figures of the top products over the calendar. | Yes |
| `Analytics/Service/ShareRounder` | Rounds shares to one decimal so that the sum is 100. | Yes |
| `Analytics/Service/AnalyticsRange` | The period: whole days, at most 366, `to` not before `from`. | Value object |
| `Analytics/Service/BucketCalendar` | Day keys and ISO Monday keys. | Yes |
| `Analytics/Infrastructure/DbalAnalyticsRepository` | The two `GROUP BY DATE()` queries for the series and the order counts query. | Yes (alias) |
| `Analytics/Infrastructure/DbalRankingRepository` | Runs the ranking statements. | Yes (alias `RankingRepositoryInterface`) |
| `Analytics/Infrastructure/RankingSql` | Builds the ranking statements as text. | Yes |
| `Analytics/Infrastructure/OxidShopCurrency` | The currency code of the shop. | Yes (alias) |
| `Analytics/Infrastructure/DailyFigures`, `OrderCountFigures`, `RankedFigure`, `ShareDayFigures` | What a repository hands over. | Value objects, not services |
| `Analytics/DataType/*` | The GraphQL types and the enums. | Not services |
| `Analytics/Exception/*` | `AnalyticsRangeException`, `AnalyticsRankingException`. | Thrown |
| `Authorization/AnalyticsRightsProvider` | The right `ADMIN_ANALYTICS_VIEW`. | Yes (tag) |
| `Shared/Service/NamespaceMapper` | Tells `graphql-base` where the controllers and types are. | Yes (tag) |
| `Module` | The module id and the version. | Constants |

## 2 Rules of the design

1. Aggregate in the database. Do not load order rows into PHP.
2. Confine every statement to the shop of the request. Use `ShopScope::equals('<column>')` and `ShopScope::parameters()`.
3. Use `AnalyticsRange` for every period. Do not parse dates in a repository.
4. Answer every bucket of the period, also an empty bucket.
5. A controller only maps. It holds no rule.
6. A GraphQL type is read-only. It has no service dependency.
7. Do not use a suppression for PHPStan, PHPCS or PHPMD. Change the code.

## 3 Services and wiring

`services.yaml` loads all of `src/` with autowire and `public: true`. It excludes `Module.php`, the folders
`DataType` and `Exception`, and the value objects of the repositories.

The interfaces have an explicit public alias. A test can then fetch them from the container of the shop.

A new value object of a repository needs a new line in the `exclude` list. The test `ModuleWiringTest` checks the list.

## 4 Procedure: add a metric to the series

1. Write a failing unit test in `tests/Unit/Analytics/AnalyticsReaderTest.php` for the new value.
2. Add the value to `DailyFigures`.
3. Add the SQL for the value to `DbalAnalyticsRepository::perDay()`. Keep the `GROUP BY DATE(...)`.
4. Sum the value in `AdminAnalyticsBucket::plus()`. Add a `#[Field]` for it.
5. Add an integration test with planted rows in `tests/Integration/AnalyticsQueriesTest.php`.
6. Update `docs/graphql-reference.md` and `docs/api/analytics.md`.
7. Run the quality gate (section 7).

## 5 Procedure: add a ranking subject (for example manufacturers)

1. Add a case to `RankingSubject`.
2. Write a failing integration test in `tests/Integration/`. Plant products, orders and the new entity. Follow `RankingFixtureTestCase`.
3. Add the grouping and the title join to `RankingSql`. Return `entityId`, `number`, `title`, `value` and `found`.
4. Add a method to `RankingReaderInterface` and `RankingReader`.
5. Add a query to `AnalyticsQueries` with `#[Query]` and `#[Right(AnalyticsRightsProvider::VIEW)]`.
6. If the assistant must read it, declare a capability (see `assistant-integration.md`).
7. Update the reference and run the gate.

> **Note:** A product that you planted in a test needs a row in `oxarticles2shop`. A category needs a row in
> `oxcategories2shop`. The shop reads both through views that join these tables.

## 6 Ranking rules in the code

| Rule | Place |
|---|---|
| Cancelled orders and items are not counted | `RankingSql::lines()` |
| A variant counts for its parent | `RankingSql::PRODUCT_OF_LINE` |
| The main category is the lowest `OXTIME` | `RankingSql::mainCategory()` |
| Sort by value, then title, then id | `RankingSql::top()` |
| Others and shares | `RankingBuilder` and `ShareRounder` |
| A deleted product has no `entityId` | `RankedFigure::gone()` and `RankingBuilder` |
| The list length is 1 to 20 | `RankingReader::MIN_LIMIT` and `MAX_LIMIT` |

## 7 Tests and quality gate

Run all commands from the SDK root, in the php container.

| Command | What it does |
|---|---|
| `docker compose exec -T php php vendor/bin/phpunit -c extensions/oxid-fe-admin-peroxid-analytics/tests/phpunit.xml --testsuite Unit` | The unit tests. They need no database. |
| The same with `--testsuite Integration` | The tests on the real database. They plant data and roll back. |
| `composer phpstan` (in the module folder) | PHPStan level 8 |
| `composer phpcs` | PSR-12 |
| `composer phpmd` | PHPMD with `--strict`. The limit for coupling is 12. |

| Test | What it proves |
|---|---|
| `Unit/Analytics/*` | The calendar, the period rules, the readers over a fake repository, the share rounding |
| `Unit/Architecture/ModuleWiringTest` | `metadata.php`, `services.yaml` and `composer.json` agree with the code |
| `Unit/Assistant/CapabilityManifestTest` | The manifest agrees with the code |
| `Integration/Top*ReaderTest` | The rankings on planted orders: cancelled items, variants, main category, other shops |
| `Integration/AssistantCapabilityTest` | The assistant reads the capabilities through its gateway |
| `Integration/AnalyticsQueriesTest`, `TopRankingQueriesTest` | The whole stack with a real admin session |

> **Note:** The tests of `AnalyticsQueriesTest` and `TopRankingQueriesTest` start the schema of the whole shop. They
> fail in a shop where a payment module has an unreadable configuration. The other tests do not.

CI (`.github/workflows/ci.yml`) has two jobs. The job `static` runs PHPCS, PHPMD and `composer audit`. The job `shop`
builds the SDK, installs the modules, and runs PHPStan, the unit tests and the integration tests. The job `shop` needs the
deploy keys for the private admin modules. Without the keys it stops with a notice.

## 8 Release checklist

1. Run the unit and integration tests.
2. Run PHPStan, PHPCS and PHPMD. They must show no error.
3. Update `CHANGELOG.md`.
4. If you change the version, change `Module::VERSION`. `metadata.php` reads it.
