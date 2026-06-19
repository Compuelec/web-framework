import { z } from "zod";
import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import type { FrameworkApiClient } from "../framework/apiClient.js";
import type { Config } from "../config.js";
import type { TokenStore } from "../auth/tokenStore.js";
import { tableNameSchema, columnNameSchema, assertNotDenied } from "../validators/identifiers.js";
import { unwrapResults } from "../utils/api.js";

const selectProjectionSchema = z
  .string()
  .refine(
    (val) =>
      val.split(",").every((part) => {
        const t = part.trim();
        return t === "*" || /^[a-z][a-z0-9_]*$/.test(t);
      }),
    { message: "select must be '*' or comma-separated column identifiers (^[a-z][a-z0-9_]*$)." },
  );

const scalarValueSchema = z.union([z.string(), z.number(), z.boolean(), z.null()]);

const recordDataSchema = z
  .record(
    z.string().regex(/^[a-z][a-z0-9_]*$/, "Field name must match ^[a-z][a-z0-9_]*$"),
    scalarValueSchema,
  )
  .refine((d) => Object.keys(d).length > 0, { message: "data must include at least one field" });

function deleteChallenge(table: string, id: string | number): string {
  return `delete-${table}-${id}`;
}

export function registerRecordTools(
  server: McpServer,
  api: FrameworkApiClient,
  cfg: Config,
  tokenStore: TokenStore,
): void {
  server.registerTool(
    "search_records",
    {
      title: "Search records in a table",
      description:
        "Reads records from any table exposed by the framework REST API. " +
        "The `table` parameter is the SQL table name — use the `title` field from `list_tables` " +
        "or `describe_table` (e.g. `admins`, `tests`), NOT the suffix. The suffix is only the " +
        "column-naming convention (`<name>_<suffix>`). " +
        "Supports filtering by column equality (`linkTo` + `equalTo`), free-text search (`linkTo` + `search`), " +
        "ordering and pagination.",
      inputSchema: {
        table: tableNameSchema,
        linkTo: columnNameSchema.optional().describe("Column name to filter or search on"),
        equalTo: z.union([z.string(), z.number()]).optional().describe("Exact value match for `linkTo`"),
        search: z.string().optional().describe("LIKE search on `linkTo`; mutually exclusive with `equalTo`"),
        orderBy: columnNameSchema.optional(),
        orderMode: z.enum(["asc", "desc"]).optional(),
        startAt: z.number().int().nonnegative().optional().describe("Offset for pagination"),
        endAt: z.number().int().positive().optional().describe("Limit for pagination"),
        select: selectProjectionSchema.optional().describe('Comma-separated column names or "*" (default "*")'),
      },
    },
    async ({ table, linkTo, equalTo, search, orderBy, orderMode, startAt, endAt, select }) => {
      assertNotDenied(table, cfg.denyTables);
      if (equalTo !== undefined && search !== undefined) {
        throw new Error("`equalTo` and `search` are mutually exclusive.");
      }
      if ((equalTo !== undefined || search !== undefined) && !linkTo) {
        throw new Error("`linkTo` is required when using `equalTo` or `search`.");
      }

      const query: Record<string, string | number | undefined> = {
        select,
        orderBy,
        orderMode,
        startAt,
        endAt,
        linkTo,
        equalTo: equalTo as string | number | undefined,
        search,
      };

      const data = await api.get(table, query);
      const rows = unwrapResults(data);

      return {
        content: [
          {
            type: "text",
            text: JSON.stringify({ table, count: rows.length, results: rows }, null, 2),
          },
        ],
      };
    },
  );

  server.registerTool(
    "get_record",
    {
      title: "Get a single record by primary key",
      description:
        "Fetches one record from a table by primary key. The `table` parameter is the SQL " +
        "table name (the `title` from `list_tables`/`describe_table`, e.g. `pages`), not the " +
        "suffix. PKs follow `id_<suffix>` (e.g. table `pages` → PK `id_page`).",
      inputSchema: {
        table: tableNameSchema,
        id_column: columnNameSchema.describe("Primary key column name, e.g. `id_page`"),
        id: z.union([z.string(), z.number()]).describe("Primary key value"),
      },
    },
    async ({ table, id_column, id }) => {
      assertNotDenied(table, cfg.denyTables);
      const data = await api.get(table, { linkTo: id_column, equalTo: id as string | number });
      const rows = unwrapResults(data);
      const row = rows[0] ?? null;
      return {
        content: [
          { type: "text", text: JSON.stringify({ table, id_column, id, found: row !== null, record: row }, null, 2) },
        ],
      };
    },
  );

  server.registerTool(
    "create_record",
    {
      title: "Create a record in a table",
      description:
        "Creates a new row in any table exposed by the framework. Requires an authenticated " +
        "MCP session (FW_AUTH_EMAIL/FW_AUTH_PASSWORD). The `table` parameter is the SQL table " +
        "name (the `title` from `list_tables`/`describe_table`, e.g. `tests`), not the suffix. " +
        "Field names follow `<name>_<suffix>` — call `describe_table` first to learn them. " +
        "Returns the framework response (typically status 200 with the inserted id).",
      inputSchema: {
        table: tableNameSchema,
        data: recordDataSchema.describe(
          "Object mapping column name → scalar value. Must include at least one field.",
        ),
      },
    },
    async ({ table, data }) => {
      assertNotDenied(table, cfg.denyTables);
      const authQuery = await tokenStore.getAuthQuery();
      const result = await api.post(table, data, authQuery);
      return {
        content: [{ type: "text", text: JSON.stringify({ table, status: "ok", result }, null, 2) }],
      };
    },
  );

  server.registerTool(
    "update_record",
    {
      title: "Update a record by primary key",
      description:
        "Updates an existing row in a table. Requires an authenticated MCP session. " +
        "Only the columns present in `data` are modified. The `table` parameter is the SQL " +
        "table name (the `title` from `list_tables`/`describe_table`), not the suffix.",
      inputSchema: {
        table: tableNameSchema,
        id_column: columnNameSchema.describe("Primary key column name, e.g. `id_page`"),
        id: z.union([z.string(), z.number()]).describe("Primary key value of the row to update"),
        data: recordDataSchema.describe("Object mapping column name → scalar value"),
      },
    },
    async ({ table, id_column, id, data }) => {
      assertNotDenied(table, cfg.denyTables);
      const authQuery = await tokenStore.getAuthQuery();
      const result = await api.put(table, data, { id: id as string | number, nameId: id_column, ...authQuery });
      return {
        content: [
          { type: "text", text: JSON.stringify({ table, id, id_column, status: "ok", result }, null, 2) },
        ],
      };
    },
  );

  server.registerTool(
    "delete_record",
    {
      title: "Delete a record (requires explicit confirm phrase)",
      description:
        "Deletes a row from a table. **Destructive.** This tool has a two-step contract: " +
        "first call it WITHOUT `confirm_phrase`; the server returns a one-time challenge string " +
        "of the form `delete-<table>-<id>`. To actually delete, call again passing that exact " +
        "string as `confirm_phrase`. Any mismatch (including extra whitespace) aborts without " +
        "touching the database. The `table` parameter is the SQL table name (the `title` from " +
        "`list_tables`/`describe_table`), not the suffix. Requires an authenticated MCP session.",
      inputSchema: {
        table: tableNameSchema,
        id_column: columnNameSchema.describe("Primary key column name, e.g. `id_page`"),
        id: z.union([z.string(), z.number()]).describe("Primary key value of the row to delete"),
        confirm_phrase: z
          .string()
          .optional()
          .describe('Must equal exactly "delete-<table>-<id>". Omit on the first call to receive the challenge.'),
      },
    },
    async ({ table, id_column, id, confirm_phrase }) => {
      assertNotDenied(table, cfg.denyTables);
      const challenge = deleteChallenge(table, id);

      if (confirm_phrase !== challenge) {
        return {
          content: [
            {
              type: "text",
              text: JSON.stringify(
                {
                  requires_confirmation: true,
                  challenge,
                  instructions:
                    `To execute the delete, call delete_record again with confirm_phrase exactly equal to "${challenge}". ` +
                    "If you typed it wrong or the user has not approved the deletion, do not retry.",
                  table,
                  id_column,
                  id,
                },
                null,
                2,
              ),
            },
          ],
        };
      }

      const authQuery = await tokenStore.getAuthQuery();
      const result = await api.delete(table, { id: id as string | number, nameId: id_column, ...authQuery });
      return {
        content: [
          {
            type: "text",
            text: JSON.stringify({ table, id, id_column, status: "deleted", result }, null, 2),
          },
        ],
      };
    },
  );
}
