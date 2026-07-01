#!/usr/bin/env bash
#
# Playground installer — sets up the contabilidad-pyme demo on top of an
# already-installed web-framework instance.
#
# Idempotent: each SQL file uses INSERT (or includes ADD-DROP-TABLE in the
# schema dump), so re-running this script overwrites prior state cleanly.
#
# What it does:
#   1. Applies the schema dump (drops + re-creates the 8 demo tables).
#   2. Loads the demo dataset (cuentas, categorías, dummies, asientos).
#   3. Reinstalls the CMS module/column/page rows so the sections show up
#      in the admin menu.
#   4. Copies the 6 demo pages from playground/pages/ to web/pages/.
#
# Usage:
#   ./playground/install.sh
#
# Requires docker-compose stack already running (wf_web, wf_db).
set -euo pipefail

# --- locate ourselves ------------------------------------------------------

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
DATA_DIR="$SCRIPT_DIR/data"
PAGES_SRC="$SCRIPT_DIR/pages"
PAGES_DST="$REPO_ROOT/web/pages"

# --- preflight -------------------------------------------------------------

if ! command -v docker >/dev/null 2>&1; then
    echo "Error: docker is required but not on PATH." >&2
    exit 1
fi

if ! docker ps --format '{{.Names}}' | grep -q '^wf_db$'; then
    echo "Error: container wf_db is not running. Start the stack first:" >&2
    echo "  docker compose -f docker-compose.dev.yml up -d" >&2
    exit 1
fi

if [[ ! -d "$DATA_DIR" ]] || [[ ! -d "$PAGES_SRC" ]]; then
    echo "Error: playground/data/ or playground/pages/ missing — check repo state." >&2
    exit 1
fi

# DB credentials match docker-compose.dev.yml. Tweak via env if you've
# changed them locally.
DB_USER="${WF_DB_USER:-root}"
DB_PASS="${WF_DB_PASS:-root}"
DB_NAME="${WF_DB_NAME:-webframework}"

run_sql_file() {
    local file="$1"
    local label="$2"
    if [[ ! -f "$file" ]]; then
        echo "Skipping $label — $file not found." >&2
        return 0
    fi
    echo "==> Applying $label ($(basename "$file"))"
    docker exec -i wf_db mariadb -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$file"
}

# --- 1. Schema + demo dataset ----------------------------------------------

run_sql_file "$DATA_DIR/01-schema.sql" "demo table schema (DROP+CREATE)"
run_sql_file "$DATA_DIR/02-data.sql"   "demo dataset (cuentas, clientes, comprobantes…)"

# --- 2. CMS metadata --------------------------------------------------------
# Reinstall modules / columns / pages rows so the sections appear in the admin
# menu and the CRUD grids render. We INSERT IGNORE-style: if the framework
# already created our rows, the dump's INSERTs will collide — that's expected
# during a fresh re-run after `framework install`. For a clean state, drop the
# DB and re-run the framework setup, then run this script.
#
# We use --force on mariadb so duplicate-key errors don't abort the rest of
# the script.
run_sql_force() {
    local file="$1"
    local label="$2"
    if [[ ! -f "$file" ]]; then return 0; fi
    echo "==> Applying $label ($(basename "$file"))"
    docker exec -i wf_db mariadb --force -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$file" \
        || echo "    (some rows may already exist — that's fine)"
}

run_sql_force "$DATA_DIR/03-cms-modules.sql" "CMS modules"
run_sql_force "$DATA_DIR/04-cms-columns.sql" "CMS columns"
run_sql_force "$DATA_DIR/05-cms-pages.sql"   "CMS pages (menu entries)"
run_sql_force "$DATA_DIR/06-users.sql"       "test users (contador, lectura)"

# --- 3. Copy public pages ---------------------------------------------------

mkdir -p "$PAGES_DST" "$PAGES_DST/_lib"
echo "==> Copying public pages → web/pages/"
for src in "$PAGES_SRC"/*.php; do
    name="$(basename "$src")"
    cp "$src" "$PAGES_DST/$name"
    echo "    $name"
done

# Shared accounting library used by /cargar-venta, /cargar-compra and
# /generar-asientos. Lives under web/pages/_lib/ so includes resolve
# relatively from any sibling page.
if [[ -d "$PAGES_SRC/_lib" ]]; then
    echo "==> Copying shared lib → web/pages/_lib/"
    for src in "$PAGES_SRC/_lib"/*.php; do
        name="$(basename "$src")"
        cp "$src" "$PAGES_DST/_lib/$name"
        echo "    _lib/$name"
    done
fi

# --- 4. Done ----------------------------------------------------------------

cat <<'EOF'

Playground installed. URLs to visit (assuming Apache on http://localhost:8080):

  Public pages
    http://localhost:8080/dashboard-contable
    http://localhost:8080/libro-diario
    http://localhost:8080/balance
    http://localhost:8080/libro-ventas              (con razón social + asiento)
    http://localhost:8080/libro-compras             (con proveedor + categoría)
    http://localhost:8080/cargar-venta              (form 1-click: insert + asiento)
    http://localhost:8080/cargar-compra             (form 1-click: insert + asiento)
    http://localhost:8080/cargar-pago               (pago a proveedor con asiento)
    http://localhost:8080/cargar-cobro               (cobro de cliente con asiento)
    http://localhost:8080/cobros-recibidos           (listado de cobros del mes)
    http://localhost:8080/generar-link-pago?venta=N  (link de pago online via Payku)
    http://localhost:8080/validacion                (chequeo automático de errores)
    http://localhost:8080/cierre-mes                (bloquea períodos declarados al SII)
    http://localhost:8080/f29                       (Formulario 29 calculado, imprimible)
    http://localhost:8080/generar-asientos          (retoque de comprobantes huérfanos)

  Admin CMS (login: admin@admin.com / admin123 if fresh)
    http://localhost:8080/cms/
    Sections appear in the left menu: Plan de Cuentas, Clientes,
    Proveedores, Categorías de gasto, Comprobantes de venta/compra,
    Asientos contables, Líneas de asiento.

  Test users for the playground pages:
    contador@empresa.cl / contador123   (puede ver + cargar comprobantes)
    lectura@empresa.cl  / lectura123    (solo consulta — sin acceso a /cargar-*)
    admin@admin.com     / admin123      (superadmin — todo)

The full story of how this was built end-to-end (MCP + custom PHP) lives
in playground/README.md.
EOF
