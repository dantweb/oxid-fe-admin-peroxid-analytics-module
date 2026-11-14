# PEROXID Analytics module: documentation

This documentation is written in ASD-STE100 Simplified Technical English. It describes the OXID module
`peroxid_analytics`. The module adds read-only analytics queries to the admin GraphQL API.

## 1 Index

| Document | Read it to | Audience |
|---|---|---|
| [admin-guide.md](admin-guide.md) | Install and activate the module, give the right, understand the data rules, find the cause of a fault | Shop administrator, system administrator |
| [graphql-reference.md](graphql-reference.md) | Find every query, argument, type and error code, with examples | Client developer |
| [developer-guide.md](developer-guide.md) | Understand the structure, add a metric or a ranking, run the tests | Module developer |
| [assistant-integration.md](assistant-integration.md) | Understand how the admin assistant reads the analytics and links products | Module developer, administrator |
| [architecture.md](architecture.md) | See the layers and the design decisions in short form | Module developer |
| [api/analytics.md](api/analytics.md) | See the short API notes that the reference extends | Client developer |

## 2 What the module does

The module answers questions about the orders of the shop. It sums the data in the database. It sends only totals.
No order, no customer and no personal datum leaves the shop.

The module has no table, no setting and no class extension. You can remove it without a trace.

## 3 Terms

Each term has one meaning in all documents of this module.

| Term | Meaning |
|---|---|
| Admin API | The GraphQL API of the module `oe_graphql_admin`. It needs an admin session. |
| Bucket | One day, or one ISO week, of a period. |
| Capability | One read that the admin assistant can run. The file `assistant.capabilities.yaml` declares it. |
| Item | One line of a ranking: a product or a category. |
| Main category | The category of a product with the lowest `OXTIME` in `oxobject2category`. |
| Measure | What a ranking counts: `SALES`, `UNITS` or `ORDERS`. |
| Others | The total of all items outside the top list of a ranking. |
| Period | The days from the first day to the last day, both included. |
| Ranking | A list of products or categories, sorted by a measure. |
| Right | A permission of the admin role. This module adds `ADMIN_ANALYTICS_VIEW`. |
| Share | The part of an item in the total, in percent, with one decimal. |
| Shop scope | The restriction of a query to the shop of the request (`ShopScope`). |
| Top list | The first N items of a ranking. N is 1 to 20. |

## 4 Safety rules

- Every query needs an admin session and the right `ADMIN_ANALYTICS_VIEW`.
- Every query reads only the shop of the request.
- Every answer is an aggregate. An answer holds no order number, no customer name and no address.
