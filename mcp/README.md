# web-framework MCP server

Servidor [Model Context Protocol](https://modelcontextprotocol.io/) que conecta
cualquier cliente MCP (Claude Desktop, Claude Code, Cursor, etc.) con el
**web-framework** (`/api` + CMS Builder), permitiendo operarlo desde un LLM.

## Tools incluidos

### Sesión

| Tool | Lo que hace |
|---|---|
| `whoami` | Reporta si la sesión MCP está autenticada, el email del admin y la expiración del JWT. |
| `mcp_login` | Abre el flow interactivo: arranca un listener loopback y devuelve la URL del CMS donde el admin autoriza el uso de su sesión actual. Sin password de por medio. |

### Lectura

| Tool | Lo que hace |
|---|---|
| `list_tables` | Lista las secciones/tablas registradas en el CMS (lee la tabla `modules`). |
| `describe_table` | Devuelve las columnas registradas para una tabla del CMS, por `id_module` o `suffix`. |
| `search_records` | Busca registros en cualquier tabla con `linkTo`/`equalTo`/`search`, orden y paginación. |
| `get_record` | Obtiene un registro por PK (`id_<suffix>`). |
| `list_pages` | Lista páginas del CMS (`pages.url_page`, `type_page`, parent, orden). |
| `read_page` | Lee una página y todos sus módulos (joins `pages` + `modules`). |

### Escritura (requieren sesión)

| Tool | Lo que hace |
|---|---|
| `create_record` | Inserta una fila en una tabla. Falla si no hay sesión activa. |
| `update_record` | Modifica una fila por PK. Falla si no hay sesión activa. |
| `delete_record` | **Destructiva.** Two-step: la primera llamada devuelve un challenge `delete-<table>-<id>`. La segunda llamada debe pasar ese string exacto como `confirm_phrase` o se rechaza. |

## Resources

Los `.md` de `docs/` se exponen para grounding del agente:

```
framework://docs/API
framework://docs/ARQUITECTURA
framework://docs/SEGURIDAD
framework://docs/AGENTE-CREAR-PAGINAS
framework://docs/CMS-Y-TABLAS
framework://docs/GENERADOR-PAGINAS
framework://docs/DESARROLLO
framework://docs/INSTALACION
framework://docs/MANUAL-USUARIO
```

## Requisitos

- Node 20+
- Una instancia del framework corriendo (local o remota).
- `api/config.php` configurado con su `api.key`.

## Instalación

```bash
cd mcp
npm install
npm run build
```

## Modos de autenticación

### Modo A — credenciales en env vars (auto-login)

Útil para clientes desatendidos (Claude Desktop, scripts). El server hace login
al arrancar y mantiene el JWT en memoria.

| Variable | Obligatoria | Descripción |
|---|---|---|
| `FW_API_BASE_URL` | sí | URL base de `/api`, sin `/` final. |
| `FW_API_KEY` | sí | Valor exacto de `api.key` en `api/config.php`. |
| `FW_AUTH_EMAIL` | sí (modo A) | Email del admin. |
| `FW_AUTH_PASSWORD` | sí (modo A) | Password del admin. |
| `FW_AUTH_TABLE` | no | Default `admins`. |
| `FW_AUTH_SUFFIX` | no | Default `admin`. |
| `FW_DENY_TABLES` | no | Default `admins,activity_logs,sessions,tokens`. |
| `FW_HTTP_TIMEOUT_MS` | no | Default `15000`. |

### Modo B — login interactivo desde el LLM

Si no hay `FW_AUTH_EMAIL`/`FW_AUTH_PASSWORD`, el server arranca igual pero los
tools de escritura quedan deshabilitados hasta que el LLM ejecute `mcp_login`:

1. El LLM llama el tool `mcp_login` (sin argumentos).
2. El server arranca un listener loopback y devuelve una URL del CMS.
3. El admin la abre en su browser, ya logueado al CMS, y confirma con un click.
4. El CMS POSTea el JWT vigente del admin al callback. El MCP lo guarda en memoria.
5. A partir de ahí los writes funcionan, sin reiniciar el server.

**No se transmite ni se persiste ninguna password en el MCP.** El JWT vive en
memoria hasta que caduque (24 h) o el proceso muera.

### Modo C — Docker / framework remoto al MCP

Si el framework corre dentro de Docker (compose, kubernetes, etc.) y el MCP
corre en el host, el callback debe usar un hostname que el container resuelva
al host:

```
FW_CALLBACK_HOST=host.docker.internal   # Docker Desktop (Mac/Windows)
```

La página `cms/mcp-setup.php` valida que el host sea loopback-equivalente:
`127.0.0.1`, `localhost`, `::1`, `host.docker.internal` o `gateway.docker.internal`.

## Registro en clientes MCP

### Claude Desktop

`~/Library/Application Support/Claude/claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "web-framework": {
      "command": "node",
      "args": ["/ruta/absoluta/al/repo/mcp/dist/index.js"],
      "env": {
        "FW_API_BASE_URL": "http://localhost:8080/api",
        "FW_API_KEY": "tu-api-key-real",
        "FW_AUTH_EMAIL": "admin@your-domain.test",
        "FW_AUTH_PASSWORD": "your-password"
      }
    }
  }
}
```

O para flujo interactivo (sin password en el config):

```json
{
  "env": {
    "FW_API_BASE_URL": "http://localhost:8080/api",
    "FW_API_KEY": "tu-api-key-real"
  }
}
```

Y el usuario invoca `mcp_login` desde la conversación cuando quiera escribir.

### MCP Inspector (debug)

```bash
cd mcp
FW_API_BASE_URL=http://localhost:8080/api \
FW_API_KEY=tu-api-key \
npx @modelcontextprotocol/inspector node dist/index.js
```

UI web local con tools, resources, request/response inspector.

## Seguridad

- El header `Authorization` lleva la API key plana (validada con `hash_equals`).
- Por defecto el deny-list bloquea `admins`, `activity_logs`, `sessions` y
  `tokens`. Configurable con `FW_DENY_TABLES`.
- Validación de identificadores con regex `^[a-z][a-z0-9_]*$` antes de mandar
  al framework.
- `select` validado por Zod: solo `*` o columnas separadas por coma.
- Transporte **stdio local** — el server no abre puertos persistentes. El único
  puerto que abre el flow `mcp_login` es loopback efímero, se cierra cuando
  recibe el callback (o tras 5 min de timeout).
- `delete_record` exige confirm phrase exacto. Mismatch (incluso un espacio)
  aborta sin tocar la DB.
- Si el JWT vence o el framework devuelve 303, los writes fallan con error
  claro pidiendo re-login.

## Próximos PRs

- **PR3** — `create_table`, `create_page` y `run_migration` envolviendo
  `tools/*.php` por `child_process` con `dry_run`.
- **PR4** — Resources de esquema (`framework://table/{n}/schema`) y prompts
  guiados (`scaffold_section`, `seed_records`).
- **PR5** — `mcp.policy.json` con allow-list por tool, audit log, transporte
  SSE autenticado.
- **PR6** — Tools de plugins (RBAC, workflow) + packaging para `npx`.

## Desarrollo

```bash
npm run dev        # tsc --watch
npm run typecheck
npm run build
npm start
```

Smoke test del handshake MCP:

```bash
( printf '%s\n' \
    '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"smoke","version":"0"}}}' \
    '{"jsonrpc":"2.0","method":"notifications/initialized"}' \
    '{"jsonrpc":"2.0","id":2,"method":"tools/list"}'
  sleep 1
) | FW_API_BASE_URL=http://localhost:8080/api FW_API_KEY=x node dist/index.js
```

Devuelve los 10 tools registrados.
