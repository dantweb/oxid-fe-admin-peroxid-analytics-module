# PEROXID Analytics module: integration with the admin assistant

This document is written in ASD-STE100 Simplified Technical English. It describes how the module `oe_admin_assistant`
reads the analytics of this module.

## 1 How it works

The file `assistant.capabilities.yaml` is in the root of the module. It declares **read capabilities**. The assistant
reads the file of each active module. A capability joins the same registry, the same right check and the same audit as
a capability written in PHP.

- The file adds no PHP and no dependency. Where the assistant is not installed, the file has no effect.
- While this module is inactive, the assistant does not offer its capabilities.
- The assistant checks the right `ADMIN_ANALYTICS_VIEW` before it runs a capability.
- A capability can only read. It cannot change data.

The format of the file is in the documentation of the assistant (`docs/api/capabilities.md`, section 6).

## 2 The capabilities

| Capability | Service and method | Inputs |
|---|---|---|
| `analytics.order_counts` | `AnalyticsReaderInterface::orderCounts` | `from`, `to` |
| `analytics.series` | `AnalyticsReaderInterface::series` | `from`, `to`, `granularity` (`DAILY` or `WEEKLY`) |
| `analytics.top_products` | `RankingReaderInterface::topProducts` | `from`, `to`, `limit` (1 to 20), `measure` (`SALES`, `UNITS`, `ORDERS`) |
| `analytics.top_categories` | `RankingReaderInterface::topCategories` | `from`, `to`, `limit` (1 to 20), `measure` |

All dates have the form `YYYY-MM-DD`. The period has at most 366 days.

### 2.1 Answers

| Capability | Summary line | Facts |
|---|---|---|
| `analytics.order_counts` | "N orders from … to … (paid, shipped, cancelled)" | None |
| `analytics.series` | Orders, sales and new customers of the period | One fact for each bucket |
| `analytics.top_products` | "The top products hold X % of the total … (others Y %)" | One fact for each product |
| `analytics.top_categories` | The same line for categories | One fact for each category |

A fact of a ranking has the label "rank. title (number)" for a product and "rank. title" for a category. Its value is
"value · share %".

## 3 Entity links

A fact can name the entity that it is about. The assistant client then shows the fact as a link to the page of the
entity. The key is `entity` in `result.facts`:

```yaml
facts:
  path: items
  label: "{{ rank }}. {{ title }} ({{ number }})"
  value: "{{ value }} · {{ share }} %"
  entity: { type: product, id: "{{ entityId }}" }
```

- `analytics.top_products` has this key. Each product line links to its product page.
- A line without an id has no link. This applies to a product that you deleted after the sale. The line keeps its title.
- `analytics.top_categories` has no `entity` key. The assistant has no category entity.
- The id must have only letters, digits, `_` and `-`. Other ids give no link.

## 4 Rules for a change

1. Keep the input names equal to the parameter names of the service method. The test `CapabilityManifestTest` checks this.
2. Keep the enum values equal to the PHP enum cases.
3. Use only paths that exist on the GraphQL types. The renderer reads a path by method, getter or public property.
4. Do not use an enum in a template. The renderer cannot write an enum as text.
5. Run the unit tests and the integration test `AssistantCapabilityTest`.

## 5 Tests

| Test | What it proves |
|---|---|
| `tests/Unit/Assistant/CapabilityManifestTest` | The right, the module id, the enum values, the source methods, the result paths and the entity keys agree with the code |
| `tests/Integration/AssistantCapabilityTest` | With both modules active, the assistant offers each capability, answers through its gateway and gives the product id as the entity of a fact |

The integration test skips when `oe_admin_assistant` is not installed.
