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
      // Deny-list config holds SQL table names (e.g. `admins`); reject by both
      // title and suffix so a denied module can't surface under either alias.
      const visible = rows.filter((r) => {
        const suffix = String(r.suffix_module ?? "").toLowerCase();
        const title = String(r.title_module ?? "").toLowerCase();
        return !cfg.denyTables.has(suffix) && !cfg.denyTables.has(title);
      });
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
        "Returns the SQL table name (`title`) and column definitions registered in the CMS " +
        "for a given module. Use the returned `title` as the `table` parameter for record tools " +
        "(`search_records`, `create_record`, etc.) — the `suffix` is only the per-column naming " +
        "convention (`<name>_<suffix>`), not the URL path. Pass either the module id (preferred) " +
        "or the table suffix.",
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
      let resolvedSuffix: string | undefined;
      let resolvedTitle: string | undefined;

      // When id_module is provided, always resolve the real suffix from the DB
      // and validate THAT against the deny-list — never the client-supplied
      // `suffix`. Otherwise a caller passing both `id_module` (of a denied table)
      // and a benign `suffix` would slip past `assertNotDenied`, since the
      // columns query keys off `moduleId`. If `suffix` is also given it must
      // match the resolved one.
      if (moduleId) {
        const modules = unwrapResults(
          await api.get("modules", { linkTo: "id_module", equalTo: moduleId }),
        ) as unknown as ModuleRow[];
        const actualSuffix = modules.length > 0 ? String(modules[0].suffix_module ?? "") : "";
        const actualTitle = modules.length > 0 ? String(modules[0].title_module ?? "") : "";
        // Collapse "not found", "no suffix" and "denied" into one identical
        // error so id_module can't be used as an oracle to enumerate which ids
        // map to denied or hidden modules — the same invisibility `list_tables`
        // and the suffix branch already uphold. Check both title and suffix
        // because the deny-list stores SQL table names while suffix_module
        // holds the column-naming convention (e.g. `admins` vs `admin`).
        const denied =
          !actualSuffix ||
          cfg.denyTables.has(actualSuffix.toLowerCase()) ||
          cfg.denyTables.has(actualTitle.toLowerCase());
        if (denied) {
          throw new Error(`No describable module for id_module ${moduleId}.`);
        }
        if (suffix && suffix.toLowerCase() !== actualSuffix.toLowerCase()) {
          throw new Error(
            `The provided suffix "${suffix}" does not match the module suffix "${actualSuffix}".`,
          );
        }
        resolvedSuffix = actualSuffix;
        resolvedTitle = actualTitle || undefined;
      } else if (suffix) {
        // Reject denied suffixes (and the matching title) before hitting the DB
        // so the error can't be used as a side-channel to probe whether a denied
        // module exists.
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
        // Fail-closed guard: an empty suffix_module (note `??` does not catch
        // "") would otherwise leave resolvedSuffix="" and skip the deny check.
        const actualSuffix = String(modules[0].suffix_module ?? "");
        const actualTitle = String(modules[0].title_module ?? "");
        if (!actualSuffix) {
          throw new Error(
            `Module with suffix "${suffix}" has no table suffix; it is not a describable table.`,
          );
        }
        assertNotDenied(actualSuffix, cfg.denyTables);
        if (actualTitle) assertNotDenied(actualTitle, cfg.denyTables);
        moduleId = resolvedId;
        resolvedSuffix = actualSuffix;
        resolvedTitle = actualTitle || undefined;
      }

      // resolvedSuffix is now guaranteed non-empty and already deny-checked above.

      const columns = unwrapResults(
        await api.get("columns", {
          linkTo: "id_module_column",
          equalTo: moduleId,
          orderBy: "id_column",
          orderMode: "asc",
        }),
      ) as unknown as ColumnRow[];

      const compact = columns.map((c) => ({
        id_column: c.id_column,
        title: c.title_column,
        alias: c.alias_column,
        type: c.type_column,
      }));

      return {
        content: [
          {
            type: "text",
            text: JSON.stringify(
              {
                id_module: moduleId,
                title: resolvedTitle,
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
