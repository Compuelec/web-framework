# Contabilidad PyMe — playground

A working end-to-end accounting application for a small Chilean business, built
on top of the web-framework. Tracks plan de cuentas, clientes, proveedores,
comprobantes de venta/compra and produces a double-entry libro diario, libro
ventas, libro compras and a cuadrable balance.

**This is a playground, not a product.** It was assembled in one afternoon as a
test of the framework + MCP server. The code is intentionally simple, has no
tests, no auth on the public pages, and exists on the
`playground/contabilidad-pyme` branch — it should never be merged into
`development`.

The `.htaccess` fix needed to make pretty URLs work on `localhost:8080` is
the only piece worth keeping; it lives on `fix/htaccess-port-suffix` and has
its own PR.

## What's in here

```
playground/
├── README.md                  this file
├── GLOSARIO.md                term reference for non-technical users
├── install.sh                 idempotent installer (re-jugable)
├── data/
│   ├── 01-schema.sql          DROP+CREATE for the 8 demo tables
│   ├── 02-data.sql            INSERTs: cuentas, dummies, asientos
│   ├── 03-cms-modules.sql     CMS menu metadata
│   ├── 04-cms-columns.sql     CRUD column definitions (alias + select matrices)
│   └── 05-cms-pages.sql       CMS pages (sidebar entries)
└── pages/
    ├── _lib/
    │   └── asientos.php       shared accounting logic (compile + insert)
    ├── balance.php            queries plan_cuentas + asiento_lineas
    ├── cargar-compra.php      1-click form: insert + asiento + file upload
    ├── cargar-venta.php       1-click form: insert + asiento + file upload
    ├── dashboard-contable.php totals of the chosen month
    ├── generar-asientos.php   button-driven asiento creation (retry/orphans)
    ├── libro-compras.php      JOIN proveedores + categorias + asientos
    ├── libro-diario.php       JOIN asientos + lineas + plan_cuentas
    └── libro-ventas.php       JOIN clientes + asientos
```

## What was used vs. handwritten

Everything that fit the framework's CRUD/template model went through the MCP
tools; the three pages that needed JOIN/aggregation queries were written by
hand.

| Layer | Built with | What for |
|---|---|---|
| 8 tables + admin CRUD | MCP `create_section` × 8 | Plan de cuentas, clientes, proveedores, categorías, comprobantes (venta/compra), asientos, líneas. |
| Demo dataset | MCP `create_record` × 51 | 22 cuentas + 6 categorías + 6 clientes + 6 proveedores + 9 ventas + 8 compras. |
| Select options + better aliases | MCP `update_record` × 12 | Tipos, naturaleza, estados, tipo_documento, "Folio (N° del documento)". |
| Attachment column on comprobantes | SQL `ALTER TABLE` + MCP `create_record` × 2 | New `archivo_*` columns of type `file` so users can attach the PDF. |
| Dashboard | Handwritten PHP | Aggregates totals for the chosen month. |
| Libro diario | Handwritten PHP | JOIN asientos + asiento_lineas + plan_cuentas. |
| Libro de ventas / compras | Handwritten PHP | JOIN to clientes / proveedores / categorías + asiento badge. (Initially generated via MCP `create_page` but rewritten when we needed razón social and JOINs.) |
| Balance | Handwritten PHP | Acumulated saldos by tipo_cuenta, cuadre check. |
| Generar asientos (retry) | Handwritten PHP | Builds double-entry for orphan comprobantes. |
| Cargar venta / compra | Handwritten PHP | 1-click form with auto-IVA, file upload, transactional insert + asiento. |
| Shared accounting library | `web/pages/_lib/asientos.php` | `compileAsientoVenta` / `compileAsientoCompra` / `insertarAsiento` / `cuentaPorCodigo` — reused by 3 pages. |

65 MCP calls + 7 PHP files written from scratch (4 pages + 2 forms + 1 lib).

## How to revive the playground

Requires the Docker dev stack (wf_web, wf_db) up — `docker compose
-f docker-compose.dev.yml up -d` from the repo root.

```bash
./playground/install.sh
```

The script:

1. drops + recreates the 8 demo tables (`01-schema.sql`);
2. loads the cuentas, dummies and previously-generated asientos (`02-data.sql`);
3. registers the CMS modules/columns/pages so the sections appear in the
   admin menu (03/04/05);
4. copies `pages/*.php` to `web/pages/`.

After running, the URLs at the bottom of the script output should respond
200.

## How the asientos work

`generar-asientos.php` reads comprobantes that have no `asientos` row pointing
at them yet and writes the double-entry transaction. The recipe per type:

```
Venta afecta (factura/boleta with IVA):
  D  Clientes              total
     H  Ventas afectas        neto
     H  IVA Débito Fiscal     iva

Venta exenta:
  D  Clientes              total
     H  Ventas exentas        exento

Compra afecta:
  D  <Categoría's cuenta>     neto
  D  IVA Crédito Fiscal       iva
     H  Proveedores              total

Compra exenta or boleta de honorarios:
  D  <Categoría's cuenta>     total
     H  Proveedores              total
```

A `Σ debe = Σ haber` check aborts the insert if the legs don't balance
(should never happen with the templates above, but it's cheap insurance).

## How a contador uses it (workflow)

Two ways to load comprobantes:

1. **Quick path — `/cargar-venta` and `/cargar-compra`.** A single page
   per comprobante: pick the type, fill folio + fecha + cliente/proveedor +
   monto neto, the IVA auto-calculates at 19% (you can override), optionally
   attach the PDF/JPG. Click "Cargar y generar asiento" and the system
   inserts the comprobante + writes the double-entry asiento in one
   transaction. If anything fails, both rollback.

2. **CMS admin path — `/cms`.** Each table appears as a section in the
   sidebar. The CRUD grid lets you list, edit, search and (re-)attach
   files. Useful for bulk corrections or for users who prefer table-style
   data entry. After loading a comprobante from here, hit
   `/generar-asientos` to create its asiento (the form-based path does it
   automatically; the CMS-loaded ones are "orphans" until you ask).

The `archivo_venta` / `archivo_compra` columns are of CMS type `file`, so
the CRUD form renders the Files Manager modal (browse-or-upload UI). Files
are stored under `cms/views/assets/files/`.

## Authentication

All 9 public pages now require login. Sessions are validated against the
framework's `admins` table — same email/password as the CMS, no parallel
user store. Three roles are wired up:

| Role | Pages allowed |
|---|---|
| `lectura` | dashboard, libros, balance, validacion (read-only) |
| `contador` | all of the above + `/cargar-venta`, `/cargar-compra`, `/generar-asientos` |
| `superadmin` / `admin` | bypass all role checks (CMS administrators) |

Test users shipped by `install.sh` (`data/06-users.sql`):

| Email | Password | Role |
|---|---|---|
| `admin@admin.com` | `admin123` | superadmin |
| `contador@empresa.cl` | `contador123` | contador |
| `lectura@empresa.cl` | `lectura123` | lectura |

To add a new user, create it from `/cms/` → Administradores, then set its
`rol_admin` to `contador` or `lectura`.

The auth layer lives in `web/pages/_lib/auth.php` and is included by every
playground page with two lines:

```php
require_once __DIR__ . '/_lib/auth.php';
wpb_require_role(['contador', 'lectura']);   // or just ['contador'] for write pages
```

`wpb_require_role([])` (empty array) means "any logged-in user". The
header shows the current user's email + role and a "Cerrar sesión" link
that hits `?wpb_logout=1`.

## Known limitations

- **No password reset** flow. Lost passwords are reset via the CMS.
- **No FK enforcement** between tables. The framework uses int columns that
  point at other tables by convention; deleting a cliente leaves dangling
  references in `comprobantes_venta`.
- **No IVA recalculation on edit.** If the user updates `neto_venta` after
  generating the asiento, the asiento is not regenerated automatically.
  Either delete + regenerate, or implement a CRUD hook.
- **No mes-cierre.** Asientos can be edited or deleted at any time. A real
  system would lock validated months.
- **One company only.** The schema doesn't carry an `empresa_id`, intentional
  for this prototype.

## Not for production

The playground was built to test the framework + MCP. To use it for real you
would, at a minimum:

- gate every public page behind login (`private: true` + `accessRoles`);
- add a CSRF token to the `generar-asientos.php` GET action (currently any
  authenticated user can re-generate by URL);
- enforce ownership: a contador shouldn't see another contador's asientos;
- add validation that comprobantes can't be edited once an asiento exists.
