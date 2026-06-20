# Production Manager — Implementation

A generic, configurable **manufacturing** plugin: produce N units of a product,
consuming the supplies (insumos) defined in its **recipe** and increasing the
product's stock — all in a single **atomic, race-safe** transaction.

> Stock semantics: supplies are consumed at **manufacturing** time (not at sale
> time). Selling is handled separately by the POS plugin.

## Files

```
plugins/production-manager/
├── production-manager.php             # plugin entry (loaded by the registry)
├── config.example.php                 # config template (copy → config.php)
├── config.php                         # YOUR mapping (local, gitignored)
├── ajax.php                           # auth + role + CSRF guarded dispatch
├── controllers/production-manager.controller.php
├── views/main.php                     # manufacturing UI
├── assets/js/production-manager.js
├── assets/css/production-manager.css
└── doc/IMPLEMENTACION.md
cms/views/pages/custom/production-manager/production-manager.php  # CMS page wrapper
plugins/plugins-registry.php                                      # registers it
```

## Install

1. Registered in `plugins/plugins-registry.php` (`url: production-manager`).
2. Create the data tables (any tables work — use the CMS module builder):
   - **products** (the output; its stock increases),
   - **supplies / insumos** (the inputs; their stock decreases),
   - **recipes** (`producto`, `insumo`, `cantidad` per 1 product unit),
   - **production** (a log: `producto`, `cantidad`, optional user/status/date).
3. Copy `config.example.php` → `config.php` and map every table/column.
4. Create a CMS page so it shows in the sidebar:
   `type_page = custom`, `url_page = production-manager`, `icon_page = bi-hammer`.

## How the atomic production works

On `produce(productId, qty)` the controller opens one transaction and, per recipe
line, runs:

```sql
UPDATE <supply> SET <stock> = <stock> - (qty * per_unit)
 WHERE <id> = :supply AND <stock> >= (qty * per_unit);
-- requires rowCount() === 1, else the whole production is rolled back
```

then `UPDATE <product> SET <stock> = <stock> + qty` and inserts a production row.
Because each supply check-and-decrement is a single statement, InnoDB row locks
serialize concurrent runs and supply stock can never go negative.

## Security

- All table/column names from config are validated against `^[a-zA-Z0-9_]+$`;
  all values are bound parameters.
- `ajax.php` requires an admin session, a role in `roles_allowed`, and a CSRF
  token on `produce`.
- `logActivity('create','production',id)` records each run.

## AJAX actions

`search_products`, `search_supplies`, `get_recipe`, `produce` (write, CSRF), `save_recipe` (write, CSRF), `get_productions`.

## UI

Three tabs: **Fabricar** (pick a product, see needed-vs-available + how many you can make now, manufacture), **Recetas** (visual recipe editor: per-batch yield + add/remove ingredients with quantities), **Historial** (recent production runs).

## Optional: batch yield

Map `product.yield` to a column holding how many units one recipe batch makes (default 1). Recipe quantities are then per batch; producing `qty` units consumes `qty * recipe_qty / yield` of each supply.
