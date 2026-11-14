# Changelog

## Unreleased

- `analytics.top_products`: each fact names its product (`entity: { type: product, id: "{{ entityId }}" }`), so the assistant's answer links to the product pages. The line of a deleted product has no link. `analytics.top_categories` has no entity: the assistant has no category entity.
- `adminAnalyticsTopProducts`, `adminAnalyticsTopCategories` and `adminAnalyticsTopShareSeries`: rankings by sales, units or
  orders with others and shares that add up to 100 (variants summed into the parent product, main category only,
  cancelled orders and items left out); `analytics.top_products` and `analytics.top_categories` for the admin assistant.
- `adminAnalyticsOrderCounts(from, to)`: total, paid, shipped and cancelled orders of a period (right
  `ADMIN_ANALYTICS_VIEW`).
- `assistant.capabilities.yaml`: `analytics.order_counts` and `analytics.series` for the OXID admin assistant (declarative
  read capabilities; no PHP, no dependency on the assistant).

## 0.1.0

First release. `adminAnalyticsSeries(from, to, granularity)` for the admin API of graphql-admin: orders, gross sales and
new customers of the shop of the request per day or ISO week, every bucket of the period, totals and ratios; the right
`ADMIN_ANALYTICS_VIEW`; no personal data; unit, wiring and integration tests; PHPStan level 8.
