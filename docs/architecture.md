# Architecture

## Where it sits

```
oxid-fe-admin ─ analytics plugin ─ /graphql/ ─► graphql-base ─► graphql-admin (admin session, #[Right], ShopScope)
                                                     │
                                                     └─► this module: NamespaceMapper (controllers, types) + AnalyticsRightsProvider
```

- **graphql-base** finds the module's controller and types through the tagged `Shared\Service\NamespaceMapper`
  (`graphql_namespace_mapper`), like every GraphQL module of the shop.
- **graphql-admin** enforces `#[Right(ADMIN_ANALYTICS_VIEW)]` with the admin session and the rights catalogue; the module
  adds its right through `Authorization\AnalyticsRightsProvider` (`graphql_admin_rights_provider`) and confines its SQL to
  the shop of the request with graphql-admin's `ShopScope`. Nothing of `oxid-admin-graphql-api` is referenced: the admin
  surface (sessions, MFA, roles) is reached only through graphql-admin's interfaces, as its `extension-points.md` asks.

## Layers (`src/`)

| Folder | What | Service? |
| --- | --- | --- |
| `Analytics/Controller/AnalyticsQueries` | the query: mapping only, `#[Query] #[Right]` | yes (public) |
| `Analytics/Service/AnalyticsReader` (+Interface) | the calendar laid over the per-day figures; totals | yes, aliased |
| `Analytics/Service/RankingReader` (+Interface), `RankingBuilder`, `ShareSeriesBuilder`, `ShareRounder` | the rankings: bounds, others, shares that add up to 100, the complete share calendar | yes, aliased |
| `Analytics/Service/AnalyticsRange` | the period: whole days, ≤ 366, `to` ≥ `from`; throws `AnalyticsRangeException` | value object |
| `Analytics/Service/BucketCalendar` | day / ISO-Monday keys, complete key lists | yes |
| `Analytics/Infrastructure/DbalAnalyticsRepository` (+Interface) | two `GROUP BY DATE()` queries, shop-scoped | yes, aliased |
| `Analytics/Infrastructure/DbalRankingRepository` (+Interface), `RankingSql` | the ranking statements over `oxorderarticles`: `GROUP BY`, `ORDER BY ... LIMIT`, sums; shop-scoped | yes, aliased |
| `Analytics/Infrastructure/DailyFigures`, `RankedFigure`, `ShareDayFigures` | what the repositories hand over | value objects (excluded from DI) |
| `Analytics/Infrastructure/OxidShopCurrency` (+Interface) | the shop's currency code | yes, aliased |
| `Analytics/DataType/*` | `AdminAnalyticsSeries`, `AdminAnalyticsBucket`, `AdminAnalyticsRanking`, `AdminAnalyticsRankItem`, `AdminAnalyticsShareSeries`, `AdminAnalyticsShareBucket`, enums `AdminAnalyticsGranularity`, `AdminAnalyticsRankMeasure` | GraphQL types, not services |
| `Analytics/Exception/AnalyticsRangeException`, `AnalyticsRankingException` | graphqlite `GraphQLException` with `errorCode` | thrown |
| `Authorization/AnalyticsRightsProvider` | `ADMIN_ANALYTICS_VIEW` | yes, tagged |
| `Shared/Service/NamespaceMapper` | folders → namespaces for graphql-base | yes, tagged |
| `Module` | id and version, read by `metadata.php` and the tests | constants |

`services.yaml` sweeps `src/*` into the container (autowired, public) and excludes `Module.php`, the DataTypes, the
exception and `DailyFigures`; the interfaces are explicit public aliases so a test can fetch them from the shop's container.

## Decisions

- **Aggregate in the database, not in PHP.** `COUNT` and `SUM` per `DATE(...)` keep the answer small and keep every order
  and customer in the shop. The reader only fills the calendar and folds days into weeks.
- **A complete calendar.** The client draws a curve; a missing day would bend it. Every bucket of the period is answered.
- **ISO weeks keyed by Monday**, as the plugin's demo data does, so both sources look the same on screen.
- **One right, read-only, no settings, no tables, no extension of a shop class.** The module can be removed without a
  trace; deactivation takes the field out of the schema (clear the schema cache).
- **Rankings count order items, not orders.** Variants fold into the parent product and a sale counts for the main
  category only, so the shares of a ranking add up to 100 and "others" shows the whole. Cancelled orders and cancelled
  items are left out, as in the sales curve.
- **A year at most.** `AnalyticsRange::MAX_DAYS = 366`: the two queries stay cheap and the answer bounded.

## Tests

| What | Where |
| --- | --- |
| calendar keys (days, ISO Mondays, complete lists) | `tests/Unit/Analytics/BucketCalendarTest` |
| period rules and the error code | `tests/Unit/Analytics/AnalyticsRangeTest` |
| reader over a fake repository: empty buckets, weekly folding, totals, ratios, a row outside the period | `tests/Unit/Analytics/AnalyticsReaderTest` |
| the right and its name outside the core catalogue | `tests/Unit/Authorization/AnalyticsRightsProviderTest` |
| the mapper's folders exist | `tests/Unit/Shared/NamespaceMapperTest` |
| metadata / services.yaml / composer.json agree with the code | `tests/Unit/Architecture/ModuleWiringTest` |
| ranking bounds, others, share rounding (sum 100), the share calendar over a fake repository | `tests/Unit/Analytics/RankingReaderTest`, `ShareRounderTest` |
| the rankings and the share curve on the real database (planted orders, rolled back): cancelled, variants, main category, shops | `tests/Integration/Top*ReaderTest` |
| the whole stack with a real admin: refusals, planted orders and a customer, weekly, invalid period | `tests/Integration/AnalyticsQueriesTest` |
