# PEROXID Analytics module: GraphQL reference

This document is written in ASD-STE100 Simplified Technical English. It lists every query, argument, type and error
code of the module.

## 1 General rules

- Send the query to `/graphql/` with the header `Authorization: Bearer <admin token>`.
- Every query needs the right `ADMIN_ANALYTICS_VIEW`.
- The scalar `DateTime` takes an ISO date. The module drops the time of day.
- A period has at most 366 days. The last day is not before the first day. Both days count.
- Every query reads the shop of the request. Use `?shp=<id>` to select the shop.
- A refusal is a GraphQL error. Read `extensions.errorCode`.

## 2 Queries

| Query | Returns | Section |
|---|---|---|
| `adminAnalyticsSeries` | Orders, sales and new customers per bucket | 2.1 |
| `adminAnalyticsOrderCounts` | Total, paid, shipped and cancelled orders | 2.2 |
| `adminAnalyticsTopProducts` | The best selling products | 2.3 |
| `adminAnalyticsTopCategories` | The best selling categories | 2.3 |
| `adminAnalyticsTopShareSeries` | The share of the top products per bucket | 2.4 |

### 2.1 adminAnalyticsSeries

```graphql
adminAnalyticsSeries(from: DateTime!, to: DateTime!, granularity: AdminAnalyticsGranularity!): AdminAnalyticsSeries!
```

| Argument | Meaning |
|---|---|
| `from` | The first day of the period |
| `to` | The last day of the period |
| `granularity` | `DAILY`: one bucket for each day. `WEEKLY`: one bucket for each ISO week, named by its Monday. |

The answer holds every bucket of the period in order. An empty bucket has zeros. The first weekly bucket can start
before `from`. It counts only the days from `from`.

Example:

```graphql
{
  adminAnalyticsSeries(from: "2026-10-01", to: "2026-10-31", granularity: WEEKLY) {
    currency
    buckets { key orders sales customers }
    totals { orders sales customers }
  }
}
```

### 2.2 adminAnalyticsOrderCounts

```graphql
adminAnalyticsOrderCounts(from: DateTime!, to: DateTime!): AdminAnalyticsOrderCounts!
```

The query counts the orders in one SQL statement. `total` includes cancelled orders. `paid` counts orders with a
payment date. `shipped` counts orders with a send date. `cancelled` counts orders with `OXSTORNO = 1`.

```graphql
{ adminAnalyticsOrderCounts(from: "2026-11-12", to: "2026-11-12") { total paid shipped cancelled } }
```

### 2.3 adminAnalyticsTopProducts and adminAnalyticsTopCategories

```graphql
adminAnalyticsTopProducts(from: DateTime!, to: DateTime!, limit: Int!, measure: AdminAnalyticsRankMeasure!): AdminAnalyticsRanking!
adminAnalyticsTopCategories(from: DateTime!, to: DateTime!, limit: Int!, measure: AdminAnalyticsRankMeasure!): AdminAnalyticsRanking!
```

| Argument | Meaning |
|---|---|
| `limit` | The length of the top list: 1 to 20 |
| `measure` | `SALES`, `UNITS` or `ORDERS` |

Rules:

- The module does not count cancelled orders and cancelled order items.
- A variant counts for its parent product.
- A sale counts for the main category of its product only.
- Items with equal values are sorted by title, then by id.

Example:

```graphql
{
  adminAnalyticsTopProducts(from: "2026-10-01", to: "2026-10-31", limit: 5, measure: SALES) {
    currency total topTotal topShare others othersShare
    items { rank entityId number title value share }
  }
}
```

Example answer (shortened):

```json
{"data":{"adminAnalyticsTopProducts":{"currency":"EUR","total":66.0,"topTotal":60.0,"topShare":90.9,
  "others":6.0,"othersShare":9.1,
  "items":[{"rank":1,"entityId":"a1","number":"A-1","title":"Alpha","value":30.0,"share":45.5}]}}}
```

### 2.4 adminAnalyticsTopShareSeries

```graphql
adminAnalyticsTopShareSeries(from: DateTime!, to: DateTime!, granularity: AdminAnalyticsGranularity!,
                             limit: Int!, measure: AdminAnalyticsRankMeasure!): AdminAnalyticsShareSeries!
```

The module ranks the products of the whole period. Then it gives, for each bucket, the part that the top `limit`
products hold. An empty bucket has the share 0.

## 3 Types

### 3.1 AdminAnalyticsSeries

| Field | Type | Meaning |
|---|---|---|
| `from`, `to` | `DateTime!` | The period, at midnight |
| `granularity` | `AdminAnalyticsGranularity!` | As asked |
| `currency` | `String!` | The ISO code of the shop currency (`EUR` if none is set) |
| `buckets` | `[AdminAnalyticsBucket!]!` | Every bucket in order |
| `totals` | `AdminAnalyticsBucket!` | The sums of the period, `key` is `total` |

### 3.2 AdminAnalyticsBucket

| Field | Type | Meaning |
|---|---|---|
| `key` | `String!` | `YYYY-MM-DD` of the day or of the Monday. `total` for the totals. |
| `orders` | `Int!` | Orders that are not cancelled |
| `sales` | `Float!` | The sum of `OXTOTALORDERSUM`, in cents |
| `customers` | `Int!` | New customer accounts |
| `ordersPerCustomer` | `Float!` | `orders / customers`, 4 decimals, 0 without customers |
| `salesPerCustomer` | `Float!` | `sales / customers`, 2 decimals, 0 without customers |

### 3.3 AdminAnalyticsOrderCounts

Fields: `from`, `to` (`DateTime!`), `total`, `paid`, `shipped`, `cancelled` (`Int!`).

### 3.4 AdminAnalyticsRankMeasure

| Value | Meaning |
|---|---|
| `SALES` | The sum of the gross item totals |
| `UNITS` | The sum of the item amounts |
| `ORDERS` | The number of different orders for each item |

### 3.5 AdminAnalyticsRanking

| Field | Type | Meaning |
|---|---|---|
| `measure` | `AdminAnalyticsRankMeasure!` | As asked |
| `currency` | `String!` | The currency of the shop. It is the unit when the measure is `SALES`. |
| `total` | `Float!` | The measure over all items of the period |
| `topTotal` | `Float!` | The measure over the items of the list |
| `topShare` | `Float!` | The percent that the items hold (the sum of their shares) |
| `others` | `Float!` | The measure outside the list |
| `othersShare` | `Float!` | The percent of `others` |
| `items` | `[AdminAnalyticsRankItem!]!` | The list, best first |

The shares of all items and `othersShare` have one decimal. They add up to exactly 100. (The method is the largest
remainder method.)

### 3.6 AdminAnalyticsRankItem

| Field | Type | Meaning |
|---|---|---|
| `rank` | `Int!` | 1 for the best |
| `entityId` | `String` | The product id or the category id. It is `null` for the line "No category" and for a product that you deleted after the sale. |
| `number` | `String` | The article number of a product. It is `null` for a category. |
| `title` | `String!` | The title in the language of the request. The line "No category" has that text. A deleted product keeps the title of its order item. |
| `value` | `Float!` | The measure of the item |
| `share` | `Float!` | The percent of the total |

### 3.7 AdminAnalyticsShareSeries and AdminAnalyticsShareBucket

`AdminAnalyticsShareSeries` has the fields `from`, `to`, `granularity`, `measure`, `buckets` and `totals`.

`AdminAnalyticsShareBucket` has these fields:

| Field | Type | Meaning |
|---|---|---|
| `key` | `String!` | The day, the Monday, or `total` |
| `value` | `Float!` | The measure of the top products in the bucket |
| `total` | `Float!` | The measure of all products in the bucket |
| `share` | `Float!` | The percent, one decimal. It is 0 for an empty bucket. |

For the measure `ORDERS`, `total` counts each pair of order and product once.

## 4 Error codes

| `errorCode` | When | Message |
|---|---|---|
| `admin_session_required` | The request has no admin token | (from `oe_graphql_admin`) |
| `permission_denied` | The role has no `ADMIN_ANALYTICS_VIEW` | (from `oe_graphql_admin`) |
| `analytics_range_invalid` | `to` is before `from`, or the period is longer than 366 days | "The period ends before it starts" or "The period is longer than 366 days" |
| `analytics_ranking_invalid` | `limit` is not 1 to 20 | "The list length must be between 1 and 20" |

## 5 What an answer never holds

An answer holds no order id, no order number, no customer id, no name, no e-mail address and no payment method.
