import { z } from "zod";
import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import type { FrameworkApiClient } from "../framework/apiClient.js";
import type { Config } from "../config.js";
import { tableNameSchema, assertNotDenied } from "../validators/identifiers.js";

type ModuleRow = Record<string, unknown> & {
  id_module?: number;
  title_module?: string;
  suffix_module?: string;
  type_module?: string;
};

type ColumnRow = Record<string, unknown> & {
  id_column?: number;
  title_column?: string;
  alias_column?: string;
  type_column?: string;
  order_column?: number;
};

function unwrapResults(payload: unknown): unknown[] {
  if (Array.isArray(payload)) return payload;
  if (payload && typeof payload === "object" && "results" in (payload as object)) {
    const r = (payload as { results: unknown }).results;
    return Array.isArray(r) ? r : [];
  }
  return [];
}

export function registerTableTools(server: McpServer, api: FrameworkApiClient, cfg: Config): void {
  server.registerTool(
    "list_tables",
    {
      title: "List tables registered in the CMS",
      description:
        "Lists every section/table registered in the CMS via the `modules` table. " +
        "Returns the module id, human title, table suffix used for column naming, and module type. " +
        "Use this before any other tool to discover what data lives in the framework.",
      inputSchema: {},
    },
    async () => {
      const data = await api.get("modules", { orderBy: "title_module", orderMode: "asc" });
      const rows = unwrapResults(data) as ModuleRow[];
      const visible = rows.filter((r) => !cfg.denyTables.has(String(r.suffix_module ?? "").toLowerCase()));
      const compact = visible.map((r) => ({
        id_module: r.id_module,
        title: r.title_module,
        suffix: r.suffix_module,
        type: r.type_module,
      }));
      return {
        content: [{ type: "text", text: JSON.stringify({ count: compact.length, modules: compact }, null, 2) }],
      };
    },
  );

  server.registerTool(
    "describe_table",
    {
      title: "Describe a CMS table's columns",
      description:
        "Returns the column definitions registered in the CMS for a given table suffix " +
        "(e.g. for table `productos` with suffix `product`, returns columns like `name_product`, " +
        "`price_product`). Pass either the module id (preferred) or the table suffix. " +
        "Use this before searching/creating records so the agent knows the real column names.",
      inputSchema: {
        id_module: z.number().int().positive().optional(),
        suffix: tableNameSchema.optional(),
      },
    },
    async ({ id_module, suffix }) => {
      if (!id_module && !suffix) {
        throw new Error("Provide either `id_module` or `suffix`.");
      }

      let moduleId = id_module;
      let resolvedSuffix = suffix;

      if (!moduleId && suffix) {
        const modules = unwrapResults(
          await api.get("modules", { linkTo: "suffix_module", equalTo: suffix }),
        ) as ModuleRow[];
        if (modules.length === 0) {
          throw new Error(`No CMS module found with suffix "${suffix}".`);
        }
        moduleId = Number(modules[0].id_module);
        resolvedSuffix = String(modules[0].suffix_module ?? suffix);
      } else if (moduleId && !resolvedSuffix) {
        const modules = unwrapResults(
          await api.get("modules", { linkTo: "id_module", equalTo: moduleId }),
        ) as ModuleRow[];
        resolvedSuffix = String(modules[0]?.suffix_module ?? "");
      }

      if (resolvedSuffix) assertNotDenied(resolvedSuffix, cfg.denyTables);

      const columns = unwrapResults(
        await api.get("columns", {
          linkTo: "id_module_column",
          equalTo: moduleId,
          orderBy: "order_column",
          orderMode: "asc",
        }),
      ) as ColumnRow[];

      const compact = columns.map((c) => ({
        id_column: c.id_column,
        title: c.title_column,
        alias: c.alias_column,
        type: c.type_column,
        order: c.order_column,
      }));

      return {
        content: [
          {
            type: "text",
            text: JSON.stringify(
              {
                id_module: moduleId,
                suffix: resolvedSuffix,
                column_count: compact.length,
                columns: compact,
              },
              null,
              2,
            ),
          },
        ],
      };
    },
  );
}
