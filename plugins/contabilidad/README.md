# Contabilidad PyMe

Plugin de contabilidad chilena con **doble partida** sobre el web-framework.
Cierra el ciclo contable completo (ventas · compras · pagos · cobros · asientos ·
libros SII · F29 · cierre de mes) y trae integración opcional con [Payku](../payku)
para cobros online.

## Features

- **Doble partida validada**: Σ Debe = Σ Haber en cada asiento (rechazo si no cuadra).
- **Ciclo comercial completo**:
  - `/cargar-venta` — Ventas afectas/exentas/boletas con asiento automático.
  - `/cargar-compra` — Compras con IVA crédito + retención 10% en boletas de honorarios.
  - `/cargar-pago` — Pagos a proveedores (caja/banco).
  - `/cargar-cobro` — Cobros de clientes (caja/banco/Payku).
- **Libros tributarios chilenos (formato SII)**:
  - Libro de ventas y compras en HTML + descarga CSV (BOM UTF-8, `;`) compatible con Nubox/Defontana/Softland.
  - Formulario 29 mensual calculado (débitos, créditos, retenciones, códigos SII).
- **Cierre de mes**: bloquea períodos ya declarados. Reapertura auditada.
- **Validación RUT** con módulo 11.
- **CSRF + roles** (contador / lectura / superadmin).
- **Payku opcional**: link de pago por venta, webhook (TODO).
- **Hooks para plugins externos**: RRHH, facturación, etc. pueden registrar sus propios tipos de asiento.

## Instalación

Desde CLI:

```bash
docker exec wf_web php /var/www/html/plugins/contabilidad/install.php install
```

Programáticamente:

```php
require_once __DIR__ . '/plugins/contabilidad/contabilidad.php';
$res = ContabilidadController::install();
// ['ok' => true, 'message' => 'Contabilidad PyMe instalada.', 'applied' => [...]]
```

**Idempotente**: correr `install` varias veces no rompe nada (usa `CREATE TABLE
IF NOT EXISTS` + upserts por `suffix_module` / `url_page` / `codigo_cuenta`).

## Estado del sistema

```bash
docker exec wf_web php /var/www/html/plugins/contabilidad/install.php status
```

Devuelve versión, si está instalado, orígenes de asiento registrados y config
resumida.

## Desinstalación

**Soft** (quita menú del CMS, conserva datos):

```bash
docker exec wf_web php /var/www/html/plugins/contabilidad/install.php uninstall
```

**Hard** (borra también todas las tablas contables — ⚠ destructivo):

```bash
docker exec wf_web php /var/www/html/plugins/contabilidad/install.php uninstall --drop
```

## Estructura

```
plugins/contabilidad/
├── contabilidad.php                  ← entry point (llama a init)
├── config.php                        ← IVA, retención, cuentas, roles, Payku…
├── install.php                       ← CLI installer (install/uninstall/status)
├── controllers/
│   └── contabilidad.controller.php   ← lifecycle + hooks + upserts CMS
├── lib/                              ← lógica compartida
│   ├── asientos.php                  ← compilaAsientoVenta/Compra/Pago/Cobro + insertar
│   ├── auth.php                      ← wpb_require_role / csrf
│   ├── cierres.php                   ← mes_esta_cerrado / cerrar_mes / chequeo
│   ├── rut.php                       ← validación módulo 11
│   └── sii.php                       ← códigos SII + CSV libros
├── sql/                              ← scripts SQL idempotentes
│   ├── install-01-schema.sql         ← 11 tablas contables (IF NOT EXISTS)
│   ├── install-02-seed.sql           ← plan de cuentas mínimo (INSERT IGNORE)
│   └── uninstall-02-schema.sql       ← DROP TABLE (solo con --drop)
├── views/main.php                    ← landing (TODO Sesión B: config screen)
├── ajax.php                          ← endpoints AJAX (TODO)
└── README.md
```

Las páginas del contador (`dashboard-contable`, `cargar-*`, `libro-*`, `f29`,
etc.) viven en `web/pages/` y siguen ahí porque el framework las sirve por
URL pública. Solo hacen `require_once __DIR__ . '/_lib/asientos.php'`, y los
shims en `web/pages/_lib/*.php` re-exportan desde este plugin.

## Configuración

Editá `config.php` para adaptar:

- `country.tax_adapter`: `sii-cl` por ahora (única implementación).
- `accounting.iva_rate`: 0.19 (Chile 2026).
- `accounting.retencion_honorarios_rate`: 0.10 (BHE Chile).
- `cuentas.*`: mapping códigos ↔ cuentas del plan.
- `access.roles_contador`, `access.roles_lectura`: roles del admin habilitados.
- `payku.integration_enabled`: mostrar botón "Generar link de pago" en libro-ventas.
- `hooks.allowed_origenes_externos`: qué otros plugins pueden registrar tipos de asiento.

## Integración con otros plugins (RRHH, etc.)

Otros plugins pueden declarar sus propios tipos de asiento:

```php
// Plugin de RRHH — al calcular una liquidación de sueldo:
ContabilidadController::registerAsientoOrigen('remuneracion', [
    'label'   => 'Remuneración de personal',
    'compile' => [RRHHController::class, 'compileAsientoRemuneracion'],
]);

// Luego el motor común (insertarAsiento) puede aceptar origen='remuneracion'
// y usar el compilador registrado.
```

Los `origen` autorizados se whitelistean en `config.php` (`hooks.allowed_origenes_externos`).

## Datos de prueba

Los fixtures del playground (clientes, proveedores, categorías, comprobantes,
asientos) están en `playground/data/02-data.sql` — los agregás corriendo:

```bash
docker exec -i wf_db mariadb -u web -pweb webframework < playground/data/02-data.sql
```

## Roadmap

- [ ] **Sesión B — CMS-native**: menú lateral del CMS, config screen visual (`/cms/contabilidad`), reemplazar `wpb_render_user_bar` por header del CMS.
- [ ] **Sesión C — Hooks + eventos**: sistema de eventos formal (`asiento.created`, `cierre.done`) para que otros plugins reaccionen.
- [ ] Webhook Payku → cobro automático (endpoint `/payku-notify` que crea cobro+asiento cuando Payku confirma).
- [ ] Cuentas por cobrar / pagar con aging (0-30, 31-60, 61-90, +90).
- [ ] Cuenta separada 1.1.05 "Cuenta Payku" para conciliar comisiones.
- [ ] Multipaís: adapters para AR (AFIP) y MX (SAT).
- [ ] Tests unitarios (`lib/rut.php`, `compileAsientoVenta`, `sii_csv_libro_ventas`).

## Licencia

Igual que el web-framework padre. Ver `LICENSE.md` en la raíz.
