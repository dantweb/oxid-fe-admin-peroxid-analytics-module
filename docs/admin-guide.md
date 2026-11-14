# PEROXID Analytics module: administrator guide

This document is written in ASD-STE100 Simplified Technical English. It tells you how to install, activate and
operate the module `peroxid_analytics`.

## 1 Requirements

| Requirement | Value |
|---|---|
| OXID eShop | 7.4 or newer |
| PHP | 8.2 or newer |
| Module `oe_graphql_base` | Installed and active (version 12) |
| Module `oe_graphql_admin` | Installed and active |
| Composer package | `dantweb/oxid-fe-admin-peroxid-analytics-module` |

> **Note:** The module cannot start without `oe_graphql_admin`. That module gives the admin session, the check of
> the right and the shop scope.

## 2 Install and activate

Do these steps in the shop root.

1. Add the module as a path repository:

   ```bash
   composer config repositories.dantweb/oxid-fe-admin-peroxid-analytics-module \
     --json '{"type":"path","url":"./extensions/oxid-fe-admin-peroxid-analytics","options":{"symlink":true}}'
   ```

2. Require the package:

   ```bash
   composer require "dantweb/oxid-fe-admin-peroxid-analytics-module:*@dev"
   ```

3. Install the module:

   ```bash
   bin/oe-console oe:module:install extensions/oxid-fe-admin-peroxid-analytics
   ```

4. Activate the module. Do this after `oe_graphql_base` and `oe_graphql_admin`:

   ```bash
   bin/oe-console oe:module:activate peroxid_analytics
   ```

5. Clear the schema cache of GraphQL:

   ```bash
   bin/oe-console oe:graphql:cache-clear
   ```

6. Remove the compiled container of the shop:

   ```bash
   rm -f var/cache/container/container_cache_shop_1.php
   ```

7. Check the result. The `Query` type of the schema must list `adminAnalyticsSeries`.

> **Caution:** Do steps 5 and 6 after every activation, deactivation and change of `services.yaml`. GraphQLite keeps
> the schema on disk. The shop keeps the compiled container. Without the steps, the old schema stays in use.

## 3 Migrations

The module has no database migration. It creates no table and no column. It only reads `oxorder`,
`oxorderarticles`, `oxarticles`, `oxobject2category`, `oxcategories` and `oxuser`.

## 4 Give the right

The module adds one right: `ADMIN_ANALYTICS_VIEW`. The description in the role editor is "Read the sales and customer
analytics (aggregated numbers per day or week, no personal data)".

1. Open the role editor of the admin.
2. Select the role.
3. Switch on `ADMIN_ANALYTICS_VIEW`.
4. Save the role.

The superadmin role has the right at once. Another role gets the right with the mutation `adminRoleModify`.

An admin without the right gets the error code `permission_denied`.

## 5 Data rules

The module follows these rules. The Analytics plugin shows the same numbers.

| Topic | Rule |
|---|---|
| Shop | Only orders of the shop of the request count. A mall admin of shop 2 never sees shop 1. |
| Cancelled orders | The module does not count an order with `OXSTORNO = 1`. It does not count a cancelled order item. The order counts query is an exception: it counts cancelled orders in `total` and in `cancelled`. |
| Sales per bucket | The sum of `OXTOTALORDERSUM` of the orders, rounded to cents. |
| Sales in a ranking | The sum of `OXBRUTPRICE` of the order items (gross item total). |
| Units | The sum of `OXAMOUNT` of the order items. |
| Orders in a ranking | The number of different orders that hold the product or the category. An order with two products counts for both products. |
| Variants | A variant counts for its parent product. |
| Deleted product | A product that you deleted after the sale stays in the ranking. The module shows the title and the article number stored on the order item. |
| Main category | A sale counts for the main category of the product only. A product without a category counts as "No category". |
| Currency | The module sums in the default currency of the shop. Every order is stored in it. The answer names the currency. |
| New customers | The module counts accounts with `OXRIGHTS = 'user'` by `OXCREATE`. Admin accounts do not count. |
| Time zone | The date columns hold the local time of the shop. A day is a calendar day of the shop. |
| Period | The first day and the last day count. The period has at most 366 days. The last day must not be before the first day. |
| Top list | The length is 1 to 20. |
| Language | A title is read in the language of the request. If the title is empty, the module uses the stored title. |

## 6 Troubleshooting

| Symptom | Cause | Action |
|---|---|---|
| The schema has no `adminAnalyticsSeries` | The module is not active, or the cache is old | Activate the module. Do the steps 5 and 6 of section 2. |
| Error `Cannot query field "adminAnalyticsTopProducts"` | The cache holds the old schema | Clear the schema cache. |
| Error code `admin_session_required` | The request has no admin token | Sign in as admin. A storefront token does not work. |
| Error code `permission_denied` | The role has no `ADMIN_ANALYTICS_VIEW` | Give the right (section 4). |
| Error code `analytics_range_invalid` | The period ends before it starts, or it is longer than 366 days | Send a shorter period. |
| Error code `analytics_ranking_invalid` | The list length is not 1 to 20 | Send a length from 1 to 20. |
| A ranking is empty | The period has no order, or all orders are cancelled | Check the period and the order dates. |
| A product title is the title of the order item | The product is deleted, or the shop map for the product is missing | Check the product. The title in the order item is the correct fallback. |
| Integration tests fail with `ModuleConfigurationUnreadableException` | A payment module has an unreadable configuration | Deactivate the payment modules for the test run. |

## 7 Remove the module

1. Deactivate the module: `bin/oe-console oe:module:deactivate peroxid_analytics`.
2. Clear the schema cache and the container (section 2, steps 5 and 6).

The queries leave the schema. No data stays behind.
