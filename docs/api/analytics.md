# Analytics API

Conventions are those of `oxid-admin-graphql` (`docs/conventions.md`): every operation has `#[Right]` and no `#[Logged]`;
a refusal is a GraphQL error with `extensions.errorCode`; dates are the `DateTime` scalar of graphqlite.

## Operations and rights

| Right | Operations |
| --- | --- |
| `ADMIN_ANALYTICS_VIEW` (domain `analytics`) | `adminAnalyticsSeries`, `adminAnalyticsOrderCounts`, `adminAnalyticsTopProducts`, `adminAnalyticsTopCategories`, `adminAnalyticsTopShareSeries` |

The right is added to the catalogue by `Authorization\AnalyticsRightsProvider` (tag `graphql_admin_rights_provider`).
Description in the role editor: "Read the sales and customer analytics (aggregated numbers per day or week, no personal
data)".

## `adminAnalyticsSeries(from: DateTime!, to: DateTime!, granularity: AdminAnalyticsGranularity!): AdminAnalyticsSeries!`

| Argument | Meaning |
| --- | --- |
| `from` | first day of the period; the time of day is dropped |
| `to` | last day of the period, included; the time of day is dropped |
| `granularity` | `DAILY`: one bucket per calendar day · `WEEKLY`: one per ISO week (Monday–Sunday), keyed by the Monday |

Rules (`Analytics\Service\AnalyticsRange`): `to` not before `from`; at most **366 days**. Otherwise error
`analytics_range_invalid` (HTTP 400 category, message "The period ends before it starts" / "The period is longer than 366 days").

### `AdminAnalyticsSeries`

| Field | Type | Meaning |
| --- | --- | --- |
| `from` | `DateTime!` | first day, midnight |
| `to` | `DateTime!` | last day, midnight |
| `granularity` | `AdminAnalyticsGranularity!` | as asked |
| `currency` | `String!` | ISO code of the shop's currency the sales are summed in (`Config::getActShopCurrencyObject()->name`, `EUR` when unset) |
| `buckets` | `[AdminAnalyticsBucket!]!` | every bucket of the period in order, empty ones as zeros |
| `totals` | `AdminAnalyticsBucket!` | the sums of the period, `key: "total"` |

### `AdminAnalyticsBucket`

| Field | Type | Source |
| --- | --- | --- |
| `key` | `String!` | `YYYY-MM-DD` of the day or of the ISO Monday; `total` for the totals |
| `orders` | `Int!` | `COUNT(*)` of `oxorder` with `OXSHOPID` = the shop of the request, `OXSTORNO = 0`, `OXORDERDATE` in the bucket |
| `sales` | `Float!` | `SUM(OXTOTALORDERSUM)` of the same rows (gross order total), rounded to cents |
| `customers` | `Int!` | `COUNT(*)` of `oxuser` with `OXSHOPID` = the shop, `OXRIGHTS = 'user'`, `OXCREATE` in the bucket |
| `ordersPerCustomer` | `Float!` | `orders / customers`, 0 when `customers = 0`, four decimals |
| `salesPerCustomer` | `Float!` | `sales / customers`, 0 when `customers = 0`, two decimals |

The date columns hold the shop's local time; a day is a calendar day of the shop. The shop of the request is graphql-admin's
`ShopScope` (`?shp=`), so a mall admin of shop 2 never sees shop 1's numbers.

### What the answer never holds

No order id, number or amount of a single order; no customer id, name, e-mail or address; no payment method. The module is
an aggregate-only API by construction (`Infrastructure\DbalAnalyticsRepository` runs two `GROUP BY DATE(...)` queries and
hands over counts and sums).

## Example

```bash
curl -s -H "Authorization: Bearer $ADMIN_TOKEN" -H 'Content-Type: application/json' \
  'https://shop.example/graphql/?skipSession=1&shp=1' \
  -d '{"query":"{ adminAnalyticsSeries(from: \"2026-10-01\", to: \"2026-10-31\", granularity: WEEKLY) { currency buckets { key orders sales customers } totals { orders sales customers } } }"}'
```

```json
{"data":{"adminAnalyticsSeries":{"currency":"EUR","buckets":[
  {"key":"2026-09-28","orders":14,"sales":4811.2,"customers":15},
  {"key":"2026-10-05","orders":108,"sales":39102.55,"customers":112},
  {"key":"2026-10-12","orders":266,"sales":98022.4,"customers":279},
  {"key":"2026-10-19","orders":146,"sales":53900.9,"customers":150},
  {"key":"2026-10-26","orders":153,"sales":57149.04,"customers":155}],
  "totals":{"orders":687,"sales":252986.09,"customers":711}}}}
```

(The first weekly bucket is the Monday before `from`: the week that contains 1 October 2026 starts on 28 September; only
the days from `from` on are counted in it.)

## `adminAnalyticsOrderCounts(from, to)`

How many orders the shop got between `from` and `to` (whole days, both included; at most 366 days): `total` (cancelled
orders included), `paid`, `shipped`, `cancelled`. One counting query over `oxorder` of the shop of the request; no order,
no customer data. Same right and errors as the series.

```graphql
query { adminAnalyticsOrderCounts(from: "2026-11-12", to: "2026-11-12") { total paid shipped cancelled } }
```

## Rankings: `adminAnalyticsTopProducts`, `adminAnalyticsTopCategories`, `adminAnalyticsTopShareSeries`

```graphql
adminAnalyticsTopProducts(from: DateTime!, to: DateTime!, limit: Int!, measure: AdminAnalyticsRankMeasure!): AdminAnalyticsRanking!
adminAnalyticsTopCategories(from: DateTime!, to: DateTime!, limit: Int!, measure: AdminAnalyticsRankMeasure!): AdminAnalyticsRanking!
adminAnalyticsTopShareSeries(from: DateTime!, to: DateTime!, granularity: AdminAnalyticsGranularity!, limit: Int!,
                             measure: AdminAnalyticsRankMeasure!): AdminAnalyticsShareSeries!
```

`limit` is 1 to 20 (else error `analytics_ranking_invalid`); the period rules are those of the series
(`analytics_range_invalid`). `measure` is the enum `SALES | UNITS | ORDERS`.

| Rule | Implementation (`Infrastructure\RankingSql`) |
| --- | --- |
| Which items | `oxorderarticles` joined to `oxorder` of the shop of the request (ShopScope), `OXORDERDATE` in the period, **`oxorder.OXSTORNO = 0` and `oxorderarticles.OXSTORNO = 0`** (cancelled orders and cancelled items are left out) |
| Sales | `SUM(oxorderarticles.OXBRUTPRICE)`, the gross item total, in the shop's default currency (every order is stored in it; `currency` of the answer) |
| Units | `SUM(oxorderarticles.OXAMOUNT)` |
| Orders | `COUNT(DISTINCT OXORDERID)` per product or category. The total is the sum over the lines, so an order with two products counts for both and the shares add up to 100 |
| Product | `COALESCE(NULLIF(oxarticles.OXPARENTID, ''), oxorderarticles.OXARTID)`: a **variant counts for its parent**. A product deleted after the sale is still a line: its article number and title are those stored on the order item, and its `entityId` is `null` (there is no page to link to) |
| Category | the **main category** of the product: of the product's `oxobject2category` rows the one with the lowest `OXTIME` (ties by id), if its category still exists; the classic "default category". Sales of products without one are the line `No category` (`entityId: null`) |
| Title | the view of the request language (`oxv_oxarticles_<shop>_<lang>`, `oxv_oxcategories_...`), the stored order item title (products) or the base title (categories) when it is empty |
| Order | value descending, then title, then id: stable for equal values |
| Others, shares | `others = total - topTotal`; every item's `share` and `othersShare` have one decimal and add up to exactly 100 (largest remainder method, `Service\ShareRounder`); `topShare` is the sum of the items' shares |
| Limit | `GROUP BY` and `ORDER BY ... LIMIT` in the database, plus one `SUM` over the grouped lines for the total |

`AdminAnalyticsRanking`: `measure`, `currency`, `total`, `topTotal`, `topShare`, `others`, `othersShare`,
`items: [AdminAnalyticsRankItem!]!` (`rank`, `entityId`, `number`, `title`, `value`, `share`).

`adminAnalyticsTopShareSeries` ranks the products of the **whole period** and returns, for every bucket of the complete
calendar (an empty bucket is 0), what those top `limit` products hold: `AdminAnalyticsShareBucket { key value total share }`
plus `totals` for the period. One `GROUP BY DATE(...)` query with the top ids.

## Admin assistant capabilities

`assistant.capabilities.yaml` declares four read capabilities for the OXID admin assistant (`oe_admin_assistant`), so
"how many orders yesterday?" and "how did sales develop last month?" are answered with this module's numbers:

| Capability | Source | Answer |
| --- | --- | --- |
| `analytics.order_counts` (`from`, `to`) | `AnalyticsReaderInterface::orderCounts` | "N orders from … to … (paid, shipped, cancelled)" |
| `analytics.series` (`from`, `to`, `granularity` DAILY/WEEKLY) | `AnalyticsReaderInterface::series` | totals + one fact per bucket |
| `analytics.top_products` (`from`, `to`, `limit`, `measure`) | `RankingReaderInterface::topProducts` | share of the top, one fact per product |
| `analytics.top_categories` (`from`, `to`, `limit`, `measure`) | `RankingReaderInterface::topCategories` | share of the top, one fact per category |

Both need `ADMIN_ANALYTICS_VIEW` (checked by the assistant's policy gateway before the call). The file adds no PHP and no
dependency: where the assistant is not installed it is ignored; while this module is inactive the assistant does not offer
the capabilities. `tests/Unit/Assistant/CapabilityManifestTest.php` ties the file to the code (right, module id, enum
cases, method parameters, result paths).

## Errors

| `errorCode` | When |
| --- | --- |
| `admin_session_required` | no admin token (a storefront token is not an admin session) |
| `permission_denied` | the session's roles do not hold `ADMIN_ANALYTICS_VIEW` |
| `analytics_range_invalid` | `to` before `from`, or more than 366 days |
| `analytics_ranking_invalid` | `limit` outside 1 to 20 |

## The client

The Analytics plugin of oxid-fe-admin (`src/liveSource.ts`) sends exactly this query for the chosen period and the period
before it, maps the buckets to its series and computes nothing else server-side. Without this module the field is unknown
and the plugin shows its demo data.
