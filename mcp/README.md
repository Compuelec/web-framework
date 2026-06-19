# web-framework MCP server

Servidor [Model Context Protocol](https://modelcontextprotocol.io/) que conecta
cualquier cliente MCP (Claude Desktop, Claude Code, Cursor, etc.) con el
**web-framework** (`/api` + CMS Builder), permitiendo operarlo desde un LLM.

Este es el **PR1** del roadmap: **scaffolding + tools de solo lectura**.
Las mutaciones (`create_record`, `update_record`, `delete_record`) llegan en el PR2.

## Tools incluidos (PR1)

| Tool | Lo que hace |
|---|---|
| `list_tables` | Lista las secciones/tablas registradas en el CMS (lee la tabla `modules`). |
| `describe_table` | Devuelve las columnas registradas para una tabla del CMS, por `id_module` o `suffix`. |
| `search_records` | Busca registros en cualquier tabla con `linkTo`/`equalTo`/`search`, orden y paginación. |
| `get_record` | Obtiene un registro por PK (`id_<suffix>`). |
| `list_pages` | Lista páginas del CMS (`pages.url_page`, `type_page`, parent, orden). |
| `read_page` | Lee una página y todos sus módulos (joins `pages` + `modules`). |

## Resources (documentación del framework)

El servidor expone los `.md` de `docs/` para que el agente pueda razonar sobre
las convenciones reales del framework:

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

## Configuración

Copiá `.env.example` y exportá las variables al lanzar el servidor (el cliente
MCP es quien las pasa en su config):

| Variable | Obligatoria | Descripción |
|---|---|---|
| `FW_API_BASE_URL` | sí | URL base de `/api`, sin `/` final. Ej.: `http://localhost/web-framework/api`. |
| `FW_API_KEY` | sí | Valor exacto de `api.key` en `api/config.php`. Se envía tal cual en el header `Authorization`. |
| `FW_DENY_TABLES` | no | Coma-separadas. Bloquea tools sobre estas tablas. Default: `admins,activity_logs,sessions,tokens`. |
| `FW_HTTP_TIMEOUT_MS` | no | Default `15000`. |

## Registro en clientes MCP

### Claude Desktop / Claude Code

Editar `~/Library/Application Support/Claude/claude_desktop_config.json`
(macOS) o equivalente en tu OS:

```json
{
  "mcpServers": {
    "web-framework": {
      "command": "node",
      "args": ["/ruta/absoluta/al/repo/mcp/dist/index.js"],
      "env": {
        "FW_API_BASE_URL": "http://localhost/web-framework/api",
        "FW_API_KEY": "tu-api-key-real"
      }
    }
  }
}
```

### Cursor

`Settings → MCP → Add server`, comando: `node`, args: `["…/mcp/dist/index.js"]`,
mismas env vars.

## Seguridad (PR1)

- El header `Authorization` lleva la API key **plana**, así como la valida
  `api/routes/routes.php` (`hash_equals`).
- Por defecto el deny-list bloquea `admins`, `activity_logs`, `sessions` y
  `tokens`. Configurable con `FW_DENY_TABLES`.
- Validación de identificadores con regex `^[a-z][a-z0-9_]*$` antes de mandar
  al framework (defensa en profundidad).
- Transporte **stdio local** únicamente — el server no abre puertos.
- Todo lo del PR1 es solo lectura: imposible mutar datos con estos tools.

## Próximos PRs

- **PR2** — `create_record`, `update_record`, `delete_record` con confirmación
  tipo-palabra obligatoria para destructivas (el usuario escribe un challenge
  exacto o se rechaza la operación).
- **PR3** — `create_table`, `create_page` y `run_migration` ejecutando los
  `tools/*.php` del framework por `child_process` con `dry_run`.
- **PR4** — Resources de esquema (`framework://tables`, `framework://table/{n}/schema`)
  y prompts (`scaffold_section`, `seed_records`).
- **PR5** — Policy file (`mcp.policy.json`), audit log, bearer para transporte SSE.
- **PR6** — Tools de plugins (RBAC, workflow) + packaging para `npx`.

## Desarrollo

```bash
npm run dev        # tsc --watch
npm run typecheck  # tsc --noEmit
npm run build
npm start          # node dist/index.js
```

Smoke test del handshake MCP:

```bash
( printf '%s\n' \
    '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"smoke","version":"0"}}}' \
    '{"jsonrpc":"2.0","method":"notifications/initialized"}' \
    '{"jsonrpc":"2.0","id":2,"method":"tools/list"}'
  sleep 1
) | FW_API_BASE_URL=http://localhost/web-framework/api FW_API_KEY=x node dist/index.js
```

Debe devolver el listado de los 6 tools en stdout.
