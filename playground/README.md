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
├── install.sh                 idempotent installer (re-jugable)
├── data/
│   ├── 01-schema.sql          DROP+CREATE for the 8 demo tables
│   ├── 02-data.sql            INSERTs: cuentas, dummies, asientos
│   ├── 03-cms-modules.sql     CMS menu metadata
│   ├── 04-cms-columns.sql     CRUD column definitions
│   └── 05-cms-pages.sql       CMS pages (sidebar entries)
└── pages/
    ├── balance.php            queries plan_cuentas + asiento_lineas
    ├── dashboard-contable.php totals of the chosen month
    ├── generar-asientos.php   button-driven asiento creation
    ├── libro-compras.php      bound to comprobantes_compra (via create_page)
    ├── libro-diario.php       JOIN asientos + lineas + plan_cuentas
    ├── libro-ventas.php       bound to comprobantes_venta (via create_page)
```

## What was used vs. handwritten

Everything that fit the framework's CRUD/template model went through the MCP
tools; the three pages that needed JOIN/aggregation queries were written by
hand.

| Layer | Built with | What for |
|---|---|---|
| 8 tables + admin CRUD | MCP `create_section` × 8 | Plan de cuentas, clientes, proveedores, categorías, comprobantes (venta/compra), asientos, líneas. |
| Demo dataset | MCP `create_record` × 51 | 22 cuentas + 6 categorías + 6 clientes + 6 proveedores + 9 ventas + 8 compras. |
| Select options | MCP `update_record` × 8 | Tipos, naturaleza, estados, tipo_documento. |
| Libro de ventas / compras | MCP `create_page` × 2 | Plain `{{#cada}}` listings — bound to the table. |
| Dashboard | Handwritten PHP | Aggregates totals for the chosen month. |
| Libro diario | Handwritten PHP | JOIN asientos + asiento_lineas + plan_cuentas. |
| Balance | Handwritten PHP | Acumulated saldos by tipo_cuenta, cuadre check. |
| Generar asientos | Handwritten PHP | Builds double-entry from each comprobante. |

61 MCP calls + 4 PHP files written from scratch.

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

## Known limitations

- **No auth** on the public pages. Anyone hitting `/balance` sees the
  numbers. Putting `private: true` on each page (via the CMS or by
  regenerating with `create_page { private: true, accessRoles: [...] }`)
  is the right next step before deploying anywhere.
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
