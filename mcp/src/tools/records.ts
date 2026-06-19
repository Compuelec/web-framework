import { z } from "zod";
import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import type { FrameworkApiClient } from "../framework/apiClient.js";
import type { Config } from "../config.js";
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

export function registerRecordTools(server: McpServer, api: FrameworkApiClient, cfg: Config): void {
  server.registerTool(
    "search_records",
    {
      title: "Search records in a table",
      description:
        "Reads records from any table exposed by the framework REST API. " +
        "Supports filtering by column equality (`linkTo` + `equalTo`), free-text search (`linkTo` + `search`), " +
        "ordering and pagination. Field names follow the framework convention `<name>_<suffix>` — call " +
        "`describe_table` first if you don't know them.",
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
        "Fetches one record from a table by primary key. Framework PKs follow `id_<suffix>` " +
        "(e.g. table `pages` → PK `id_page`). Use `describe_table` to confirm the suffix.",
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
}
