import { z } from "zod";
import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import type { FrameworkApiClient } from "../framework/apiClient.js";
import type { Config } from "../config.js";
import { tableNameSchema, assertNotDenied } from "../validators/identifiers.js";
import { unwrapResults } from "../utils/api.js";

type ModuleRow = {
  id_module?: number;
  title_module?: string;
  suffix_module?: string;
  type_module?: string;
};

type ColumnRow = {
  id_column?: number;
  title_column?: string;
  alias_column?: string;
  type_column?: string;
  order_column?: number;
};

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
      const rows = unwrapResults(data) as unknown as ModuleRow[];
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

      // When id_module is provided, always resolve the real suffix from the DB and
      // validate THAT against the deny-list — never the client-supplied `suffix`.
      // Otherwise a caller passing both `id_module` (of a denied table) and a benign
      // `suffix` would slip past `assertNotDenied`, since the columns query keys off
      // `moduleId`. If `suffix` is also given it must match the resolved one.
      if (moduleId) {
        const modules = unwrapResults(
          await api.get("modules", { linkTo: "id_module", equalTo: moduleId }),
        ) as unknown as ModuleRow[];
        if (modules.length === 0) {
          throw new Error(`No CMS module found with id_module ${moduleId}.`);
        }
        const actualSuffix = String(modules[0].suffix_module ?? "");
        // Fail closed: a module with no suffix has no columns table to describe,
        // and an empty `resolvedSuffix` would later skip `assertNotDenied`,
        // re-opening the deny-list bypass through a suffix-less module.
        if (!actualSuffix) {
          throw new Error(
            `Module with id_module ${moduleId} has no table suffix; it is not a describable table.`,
          );
        }
        // Deny-list check first, so a mismatch error can never leak the real
        // suffix of a denied module.
        assertNotDenied(actualSuffix, cfg.denyTables);
        if (suffix && suffix.toLowerCase() !== actualSuffix.toLowerCase()) {
          throw new Error(
            `The provided suffix "${suffix}" does not match the module suffix "${actualSuffix}".`,
          );
        }
        resolvedSuffix = actualSuffix;
      } else if (suffix) {
        // Reject denied suffixes before hitting the DB, so the error can't be
        // used as a side-channel to probe whether a denied module exists.
        assertNotDenied(suffix, cfg.denyTables);
        const modules = unwrapResults(
          await api.get("modules", { linkTo: "suffix_module", equalTo: suffix }),
        ) as unknown as ModuleRow[];
        if (modules.length === 0) {
          throw new Error(`No CMS module found with suffix "${suffix}".`);
        }
        const resolvedId = Number(modules[0].id_module);
        if (!Number.isInteger(resolvedId) || resolvedId <= 0) {
          throw new Error(`Module with suffix "${suffix}" returned an invalid id_module.`);
        }
        // Same fail-closed guard as the id_module branch: an empty suffix_module
        // (note `??` does not catch "") would leave resolvedSuffix="" and skip
        // assertNotDenied below.
        const actualSuffix = String(modules[0].suffix_module ?? "");
        if (!actualSuffix) {
          throw new Error(
            `Module with suffix "${suffix}" has no table suffix; it is not a describable table.`,
          );
        }
        assertNotDenied(actualSuffix, cfg.denyTables);
        moduleId = resolvedId;
        resolvedSuffix = actualSuffix;
      }

      // resolvedSuffix is now guaranteed non-empty and already deny-checked above.

      const columns = unwrapResults(
        await api.get("columns", {
          linkTo: "id_module_column",
          equalTo: moduleId,
          orderBy: "order_column",
          orderMode: "asc",
        }),
      ) as unknown as ColumnRow[];

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
