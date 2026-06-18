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
