import { z } from "zod";
import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import type { Config } from "../config.js";
import type { FrameworkApiClient } from "../framework/apiClient.js";
import { CliRunner, parseCliJson } from "../framework/cliRunner.js";
import { assertNotDenied } from "../validators/identifiers.js";
import { unwrapResults } from "../utils/api.js";

const FIELD_TYPES = [
  "text",
  "textarea",
  "int",
  "double",
  "money",
  "boolean",
  "date",
  "time",
  "email",
  "link",
  "color",
  "select",
  "image",
  "multiimage",
  "file",
  "object",
  "json",
  "relations",
  "order",
] as const;

const identifierSchema = z.string().regex(/^[a-z][a-z0-9_]*$/, "Must be lowercase letters/digits/underscore starting with a letter.");
const slugSchema = z.string().regex(/^[a-z0-9_-]+$/, "Lowercase letters, digits, underscores or hyphens only.");

const fieldSchema = z.object({
  name: identifierSchema.describe("Field name without the table suffix (e.g. `precio`, the framework appends `_<suffix>`)."),
  type: z.enum(FIELD_TYPES).describe("Framework field type. See docs/AGENTE-CREAR-PAGINAS.md for semantics."),
  alias: z.string().optional().describe("Friendly column label in the CMS list (defaults to `name` without the suffix)."),
  visible: z.boolean().optional().describe("Whether the column shows in the CRUD grid (default true)."),
});

function requireCliConfig(cfg: Config): { phpCmd: string; repoRoot: string } {
  if (!cfg.phpCmd || !cfg.repoRoot) {
    throw new Error(
      "This tool needs FW_PHP_CMD and FW_REPO_ROOT configured. " +
        "FW_PHP_CMD is the command that runs PHP (e.g. \"php\" or \"docker exec -i wf_web php\"); " +
        "FW_REPO_ROOT is the framework checkout path in that environment (e.g. \"/var/www/html\").",
    );
  }
  return { phpCmd: cfg.phpCmd, repoRoot: cfg.repoRoot };
}

function renderResult(scriptName: string, configJson: string, raw: unknown, command: string, exitCode: number, stderr: string) {
  if (raw && typeof raw === "object" && "success" in (raw as object)) {
    const body = raw as { success: boolean };
    const ok = body.success === true;
    return {
      isError: !ok,
      content: [
        {
          type: "text" as const,
          text: JSON.stringify(
            ok
              ? { tool: scriptName, status: "ok", result: raw, exit_code: exitCode }
              : { tool: scriptName, status: "error", result: raw, exit_code: exitCode, command, stderr_tail: stderr.slice(-400) },
            null,
            2,
          ),
        },
      ],
    };
  }
  return {
    isError: exitCode !== 0,
    content: [
      {
        type: "text" as const,
        text: JSON.stringify(
          {
            tool: scriptName,
            status: exitCode === 0 ? "ok_unparsed" : "error",
            exit_code: exitCode,
            command,
            sent_config: JSON.parse(configJson),
            stdout_tail: typeof raw === "string" ? (raw as string).slice(-400) : null,
            stderr_tail: stderr.slice(-400),
          },
          null,
          2,
        ),
      },
    ],
  };
}

/**
 * Best-effort lookup of registered CMS sections so `needs_confirmation` can
 * surface real choices to the LLM. Failures fall back to an empty list — the
 * gate still works, the LLM just won't see suggestions.
 */
async function fetchSectionSuggestions(api: FrameworkApiClient, cfg: Config): Promise<string[]> {
  try {
    const data = await api.get("modules", { orderBy: "title_module", orderMode: "asc" });
    const rows = unwrapResults(data) as unknown as { title_module?: string; suffix_module?: string }[];
    return rows
      .map((r) => String(r.title_module ?? ""))
      .filter((t) => t !== "")
      .filter((t) => !cfg.denyTables.has(t.toLowerCase()));
  } catch {
    return [];
  }
}

export function registerScaffoldTools(server: McpServer, cfg: Config, api: FrameworkApiClient): void {
  server.registerTool(
    "create_section",
    {
      title: "Create a CMS section (MySQL table + admin CRUD)",
      description:
        "Wraps `tools/make-table.php`: creates a MySQL table, registers a CMS section, and " +
        "exposes the CRUD grid in the admin menu. Returns the resolved column names so the " +
        "follow-up `create_page` call can reference them. Pass `dry_run: true` to preview the " +
        "JSON config and the CLI command without touching the database. Honors the deny-list " +
        "for the new section name. See `framework://agent/guide` for field types and conventions.",
      inputSchema: {
        name: identifierSchema.describe("Table name AND section URL slug, e.g. `productos`."),
        title: z.string().optional().describe("Title shown in the admin menu (default: capitalized `name`)."),
        icon: z.string().optional().describe('Bootstrap icon class, e.g. `bi bi-box-seam` (default `bi bi-table`).'),
        suffix: identifierSchema.optional().describe("Column-naming suffix (default: singular of `name`)."),
        fields: z.array(fieldSchema).min(1).describe("At least one field. The framework adds the PK, created_at and updated_at automatically."),
        dry_run: z.boolean().optional().describe("If true, return the JSON config and CLI command without running the script."),
      },
    },
    async ({ name, title, icon, suffix, fields, dry_run }) => {
      assertNotDenied(name, cfg.denyTables);
      if (suffix) assertNotDenied(suffix, cfg.denyTables);

      const payload: Record<string, unknown> = { name, fields };
      if (title !== undefined) payload.title = title;
      if (icon !== undefined) payload.icon = icon;
      if (suffix !== undefined) payload.suffix = suffix;
      const configJson = JSON.stringify(payload);

      if (dry_run) {
        const { phpCmd, repoRoot } = cfg.phpCmd && cfg.repoRoot
          ? { phpCmd: cfg.phpCmd, repoRoot: cfg.repoRoot }
          : { phpCmd: "<FW_PHP_CMD not set>", repoRoot: "<FW_REPO_ROOT not set>" };
        return {
          content: [
            {
              type: "text",
              text: JSON.stringify(
                {
                  tool: "create_section",
                  status: "dry_run",
                  command: `${phpCmd} ${repoRoot}/tools/make-table.php`,
                  stdin_config: payload,
                  note:
                    "Re-run with dry_run omitted (or false) to apply. The script will create " +
                    "the table, the section page, the breadcrumbs/tables modules and the columns rows.",
                },
                null,
                2,
              ),
            },
          ],
        };
      }

      const { phpCmd, repoRoot } = requireCliConfig(cfg);
      const runner = new CliRunner(phpCmd, repoRoot, cfg.cliTimeoutMs);
      const result = await runner.run("tools/make-table.php", configJson);
      const parsed = parseCliJson(result);
      return renderResult("create_section", configJson, parsed ?? result.stdout, result.command, result.exitCode, result.stderr);
    },
  );

  server.registerTool(
    "create_page",
    {
      title: "Create or replace a public page (web/pages/<name>.php)",
      description:
        "Wraps `tools/make-page.php`: generates a public page under `web/pages/<name>.php` with " +
        "the visual-builder format so the CMS lists and edits it. Optionally binds the page to a " +
        "CMS section's table (enables `{{field}}`/`{{#cada}}` template helpers and forms). " +
        "Pass `dry_run: true` to preview without writing the file. See `framework://agent/guide` " +
        "for the template tag reference. **Important:** before calling without `table`, ask the " +
        "user whether the page needs data (use `create_section` first), can be wired to an " +
        "existing section (pass `table`), or is truly static (pass `confirmedStatic: true`). " +
        "Calling without `table` and without `confirmedStatic` returns `needs_confirmation`.",
      inputSchema: {
        name: slugSchema.describe("File slug and URL path. The page lives at `web/pages/<name>.php`."),
        heading: z.string().optional().describe("Browser tab title and label in the CMS list."),
        template: z.string().optional().describe("Page HTML body. Supports the framework's template tags."),
        table: identifierSchema.optional().describe("Bind the page to a CMS section's table (the value previously passed as `name` to create_section)."),
        customCss: z.string().optional().describe("CSS injected into the page."),
        customJs: z.string().optional().describe("JavaScript injected into the page."),
        metaTitle: z.string().optional(),
        metaDesc: z.string().optional(),
        ogTitle: z.string().optional(),
        ogType: z.string().optional(),
        ogDesc: z.string().optional(),
        ogImage: z.string().optional(),
        private: z.boolean().optional().describe("If true, the page requires a CMS login to view."),
        accessRoles: z.array(z.string()).optional().describe("Admin roles allowed when `private` is true."),
        accessUsers: z.array(z.union([z.string(), z.number()])).optional().describe("Admin ids allowed when `private` is true."),
        isHome: z.boolean().optional().describe("If true, also set this page as the site home."),
        confirmedStatic: z.boolean().optional().describe("Set to true to acknowledge that the page is intentionally static (no data binding). Required when `table` is omitted; otherwise the tool returns `needs_confirmation`."),
        dry_run: z.boolean().optional().describe("If true, return the JSON config and CLI command without writing the file."),
      },
    },
    async (args) => {
      const { dry_run, table, confirmedStatic, ...rest } = args;
      if (table) assertNotDenied(table, cfg.denyTables);

      // Gate: refuse to silently create a page with no data binding. The LLM must
      // either pick an existing table or explicitly confirm the page is static.
      // `dry_run` bypasses the gate so the LLM can still preview both shapes.
      if (!table && !confirmedStatic && !dry_run) {
        const suggestions = await fetchSectionSuggestions(api, cfg);
        return {
          isError: true,
          content: [
            {
              type: "text",
              text: JSON.stringify(
                {
                  tool: "create_page",
                  status: "needs_confirmation",
                  reason:
                    "Page has no `table`. Ask the user which path to take, then re-run this tool:",
                  options: [
                    {
                      choice: "bind_existing",
                      description:
                        "Wire the page to an existing CMS section. Re-run with `table: \"<suffix>\"`.",
                      available_sections: suggestions,
                    },
                    {
                      choice: "create_new_section",
                      description:
                        "Create a new CMS section first (call `create_section`), then re-run `create_page` with that section's `name` as `table`.",
                    },
                    {
                      choice: "static",
                      description:
                        "Page is intentionally static (landing, contact, info). Re-run with `confirmedStatic: true`.",
                    },
                  ],
                  hint:
                    "Do not pick on the user's behalf. Surface the three options and wait for their decision.",
                },
                null,
                2,
              ),
            },
          ],
        };
      }

      const payload: Record<string, unknown> = { ...rest };
      if (table !== undefined) payload.table = table;
      if (confirmedStatic && !table) payload.confirmedStatic = true;
      const configJson = JSON.stringify(payload);

      if (dry_run) {
        const { phpCmd, repoRoot } = cfg.phpCmd && cfg.repoRoot
          ? { phpCmd: cfg.phpCmd, repoRoot: cfg.repoRoot }
          : { phpCmd: "<FW_PHP_CMD not set>", repoRoot: "<FW_REPO_ROOT not set>" };
        return {
          content: [
            {
              type: "text",
              text: JSON.stringify(
                {
                  tool: "create_page",
                  status: "dry_run",
                  command: `${phpCmd} ${repoRoot}/tools/make-page.php`,
                  stdin_config: payload,
                  note:
                    "Re-run with dry_run omitted (or false) to apply. The script will write " +
                    "`web/pages/<name>.php`; if `table` was supplied it also reads the columns to " +
                    "wire up the template helpers.",
                },
                null,
                2,
              ),
            },
          ],
        };
      }

      const { phpCmd, repoRoot } = requireCliConfig(cfg);
      const runner = new CliRunner(phpCmd, repoRoot, cfg.cliTimeoutMs);
      const result = await runner.run("tools/make-page.php", configJson);
      const parsed = parseCliJson(result);
      return renderResult("create_page", configJson, parsed ?? result.stdout, result.command, result.exitCode, result.stderr);
    },
  );
}
