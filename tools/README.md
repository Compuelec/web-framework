# tools

Command-line helpers for development. Each is dependency-free and runnable
with `php tools/<script>.php`.

## make-web-page.php

Scaffolds a public frontend listing page (`web/pages/<name>.php`) for a
dynamic table, following the project's conventions: it fetches via
`ApiController`, renders Bootstrap cards, escapes all output with
`htmlspecialchars`, and includes the shared `web/views/template.php`.

This automates what previously had to be hand-written for every table —
the public-frontend counterpart of the CMS's table builder.

### Usage

```bash
php tools/make-web-page.php <table> [options]
```

| Option | Description |
|--------|-------------|
| `--suffix=<s>` | Column suffix (default: derived from the table, e.g. `products` → `product`). |
| `--id=<col>` | Primary-key column (default: `id_<suffix>`). |
| `--title=<col>` | Column used as the card title (default: `name_<suffix>`). |
| `--name=<file>` | Output file name without extension (default: `<table>`). |
| `--force` | Overwrite the output file if it already exists. |
| `--stdout` | Print to stdout instead of writing a file (preview). |

### Examples

```bash
# Generate web/pages/products.php for the `products` table
php tools/make-web-page.php products

# Preview without writing, with explicit columns
php tools/make-web-page.php orders --suffix=order --title=ref_order --stdout
```

The generator validates that the table/column names are safe identifiers and
refuses to overwrite an existing page unless `--force` is given.

Tests live in `tests/generator_test.php` (run via `php tests/run.php`).

## make-migration.php

Scaffolds a `CREATE TABLE` migration in `migrations/` following the project's
conventions: the suffix column naming (`id_<suffix>`, `<col>_<suffix>`),
aligned types, `date_created_*`/`date_updated_*` timestamps, and a commented
`ROLLBACK`.

### Usage

```bash
php tools/make-migration.php <table> [options]
```

| Option | Description |
|--------|-------------|
| `--suffix=<s>` | Column suffix (default: derived from the table). |
| `--columns=<csv>` | Comma list of `name:type` pairs. |
| `--name=<file>` | Output file name (default: `create_<table>_table`). |
| `--date=<Y-m-d>` | Header date stamp (default: today). |
| `--force` | Overwrite the output file if it exists. |
| `--stdout` | Print to stdout instead of writing a file. |

Supported types: `string`/`text`, `textarea`/`longtext`, `int`, `double`,
`money`, `bool`, `date`, `datetime`, `time`, `email`.

### Example

```bash
php tools/make-migration.php products --suffix=product \
    --columns="name:string,price:money,active:bool,description:textarea"
```

Review the generated file, then apply it with `run_migration.php` or your
migration process. Tests live in `tests/migration_generator_test.php`.

## make-plugin.php

Scaffolds a complete, self-contained plugin under `plugins/<name>/` (entry
file, config, controller, view, an AJAX handler with a session guard, and a
protective `.htaccess`) and registers it in `plugins/plugins-registry.php`.

### Usage

```bash
php tools/make-plugin.php <plugin-name> [options]
```

| Option | Description |
|--------|-------------|
| `--label=<s>` | Human-readable name (default: derived from the kebab-case name). |
| `--desc=<s>` | Plugin description. |
| `--icon=<s>` | Bootstrap icon class (default: `bi-puzzle`). |
| `--type=<s>` | `custom` \| `system` \| `payment` (default: `custom`). |
| `--author=<s>` | Author (default: `Web Framework`). |
| `--force` | Overwrite existing plugin files. |
| `--no-register` | Don't touch the registry; print the snippet instead. |

The plugin name must be kebab-case (e.g. `my-plugin`). Registration is
idempotent — re-running skips the registry update if the plugin is already
registered.

### Example

```bash
php tools/make-plugin.php my-plugin --label="My Plugin" --icon=bi-star
```

Tests live in `tests/plugin_generator_test.php`.
