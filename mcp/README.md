# web-framework MCP server

Servidor [Model Context Protocol](https://modelcontextprotocol.io/) que conecta
cualquier cliente MCP (Claude Desktop, Claude Code, Cursor, etc.) con el
**web-framework** (`/api` + CMS Builder), permitiendo operarlo desde un LLM.

## Tools incluidos

### Sesión

| Tool | Lo que hace |
|---|---|
| `whoami` | Reporta si la sesión MCP está autenticada, el email del admin y la expiración del JWT. |
| `mcp_login` | **Única forma de autenticar.** Arranca un listener loopback y devuelve la URL del CMS donde el admin autoriza el uso de su sesión actual. Sin password de por medio. |

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
| `delete_record` | **Destructiva.** Two-step: la primera llamada devuelve un nonce random `delete-<table>-<id>-<hex>` con TTL 5 min. La segunda llamada debe pasar ese string exacto como `confirm_phrase` o se rechaza. |

### Scaffolding para agentes (requieren `FW_PHP_CMD` + `FW_REPO_ROOT`)

| Tool | Lo que hace |
|---|---|
| `create_section` | Envuelve `tools/make-table.php`: crea tabla MySQL + sección CMS con CRUD automático. `dry_run:true` previsualiza sin tocar la DB. Respeta el deny-list. |
| `create_page` | Envuelve `tools/make-page.php`: genera `web/pages/<name>.php` con el formato del visual-builder (queda editable desde el CMS). Opcionalmente bindea la página a una tabla. |

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

# Stable alias para el LLM — apunta a la misma fuente que docs/AGENTE-CREAR-PAGINAS.
# Leerlo antes de invocar create_section / create_page.
framework://agent/guide
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

## Autenticación

El MCP **no acepta credenciales por env vars**. La única forma de obtener una
sesión es el flujo interactivo `mcp_login`:

1. El LLM llama el tool `mcp_login` (sin argumentos).
2. El server arranca un listener loopback y devuelve una URL del CMS.
3. El admin la abre en su browser, ya logueado al CMS, y confirma con un click.
4. El CMS POSTea el JWT vigente del admin al callback. El MCP lo guarda en memoria.
5. A partir de ahí los writes funcionan, sin reiniciar el server.

**No se transmite ni se persiste ninguna password en el MCP.** El JWT vive en
memoria hasta que caduque (24 h) o el proceso muera; si eso pasa, el LLM vuelve
a invocar `mcp_login`.

| Variable | Obligatoria | Descripción |
|---|---|---|
| `FW_API_BASE_URL` | sí | URL base de `/api`, sin `/` final. |
| `FW_API_KEY` | sí | Valor exacto de `api.key` en `api/config.php`. |
| `FW_DENY_TABLES` | no | Default `admins,activity_logs,sessions,tokens`. |
| `FW_HTTP_TIMEOUT_MS` | no | Default `15000`. |
| `FW_CALLBACK_HOST` | no | Default `127.0.0.1`. Usar `host.docker.internal` cuando el framework corre en Docker Desktop. |
| `FW_OPEN_BROWSER` | no | `1`/`true` para que `mcp_login` abra el browser default. |

### Docker / framework remoto al MCP

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
        "FW_API_KEY": "tu-api-key-real"
      }
    }
  }
}
```

El usuario invoca `mcp_login` desde la conversación cuando quiera escribir.

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

- **PR4** — `run_migration` (con `dry_run` y allow-list de migration files);
  resources de esquema (`framework://table/{n}/schema`) y prompts guiados
  (`scaffold_section`, `seed_records`).
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
