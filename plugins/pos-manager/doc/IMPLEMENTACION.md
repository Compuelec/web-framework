# POS Manager — Implementation

A generic, configurable Point-of-Sale plugin: a cashier searches products, builds
a cart, and confirms a sale; the sale and its lines are recorded and each
product's stock is decremented **atomically and race-safely**. The plugin is
data-agnostic — it works with any tables you map in `config.php`.

## Files

```
plugins/pos-manager/
├── pos-manager.php                    # plugin entry (loaded by the registry)
├── config.example.php                 # config template (copy → config.php)
├── config.php                         # YOUR mapping (local, gitignored)
├── ajax.php                           # auth + role + CSRF guarded dispatch
├── controllers/pos-manager.controller.php
├── views/main.php                     # cashier UI
├── assets/css/pos-manager.css
├── assets/js/pos-manager.js
└── doc/IMPLEMENTACION.md
cms/views/pages/custom/pos-manager/pos-manager.php   # CMS page wrapper
plugins/plugins-registry.php                          # registers 'pos-manager'
```

## Install

1. The plugin is registered in `plugins/plugins-registry.php` (`url: pos-manager`).
2. Create the data tables it will use (products + sales + sale lines). Any
   tables work — e.g. create them with the CMS module builder.
3. Copy `config.example.php` → `config.php` and map every `table`/column to your
   tables (see the config contract). `config.php` is gitignored.
4. Create a CMS page so the register shows in the sidebar:
   `type_page = custom`, `url_page = pos-manager`, `icon_page = bi-cash-coin`,
   `title_page = Caja (POS)`.

## Configuration

See `config.example.php`. Groups: `product`, `sale`, `sale_item` (table + column
names), plus `roles_allowed`, `payment_methods`, `completed_status`.

## How the atomic stock decrement works

On `create_sale` the controller opens one PDO transaction and, per line, runs:

```sql
UPDATE <product_table>
   SET <stock> = <stock> - :qty
 WHERE <id> = :pid AND <stock> >= :qty AND <active> = 1;
-- requires rowCount() === 1, else the whole sale is rolled back
```

Because the check (`stock >= qty`) and the decrement are a single statement,
InnoDB row locks serialize concurrent sales of the last unit: exactly one wins
and stock never goes negative. Prices and totals are recomputed server-side and
never trusted from the client.

## Security

- All table/column names from config are validated against `^[a-zA-Z0-9_]+$`
  before use; all values are bound parameters (no string interpolation of data).
- `ajax.php` requires an admin session, a role in `roles_allowed`, and a valid
  CSRF token on `create_sale`.
- Sales are immutable (no edit/void in this version).
- `logActivity('create','sale',id)` records each sale.

## AJAX actions

`search_products` (read), `create_sale` (write, CSRF), `get_receipt` (read).
See the contracts in `specs/002-bakery-management/contracts/` for the full shapes.
