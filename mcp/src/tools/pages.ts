import { z } from "zod";
import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import type { FrameworkApiClient } from "../framework/apiClient.js";

type PageRow = Record<string, unknown> & {
  id_page?: number;
  title_page?: string;
  url_page?: string;
  type_page?: string;
  parent_page?: number;
  order_page?: number;
};

type ModuleRow = Record<string, unknown> & {
  id_module?: number;
  type_module?: string;
  title_module?: string;
  suffix_module?: string;
  width_module?: number;
};

function unwrapResults(payload: unknown): unknown[] {
  if (Array.isArray(payload)) return payload;
  if (payload && typeof payload === "object" && "results" in (payload as object)) {
    const r = (payload as { results: unknown }).results;
    return Array.isArray(r) ? r : [];
  }
  return [];
}

export function registerPageTools(server: McpServer, api: FrameworkApiClient): void {
  server.registerTool(
    "list_pages",
    {
      title: "List CMS pages",
      description:
        "Lists every page registered in the CMS (admin sections, public pages, dashboards). " +
        "Returns id, title, url and parent for each page, ordered by `order_page`.",
      inputSchema: {
        type: z
          .string()
          .optional()
          .describe('Optional filter on `type_page` (e.g. "modular", "web", "dashboard").'),
      },
    },
    async ({ type }) => {
      const query: Record<string, string | number | undefined> = {
        orderBy: "order_page",
        orderMode: "asc",
      };
      if (type) {
        query.linkTo = "type_page";
        query.equalTo = type;
      }
      const data = await api.get("pages", query);
      const rows = unwrapResults(data) as PageRow[];
      const compact = rows.map((p) => ({
        id_page: p.id_page,
        title: p.title_page,
        url: p.url_page,
        type: p.type_page,
        parent: p.parent_page,
        order: p.order_page,
      }));
      return {
        content: [{ type: "text", text: JSON.stringify({ count: compact.length, pages: compact }, null, 2) }],
      };
    },
  );

  server.registerTool(
    "read_page",
    {
      title: "Read a CMS page with its modules",
      description:
        "Returns the page metadata plus every module that belongs to it (loaded from the `modules` " +
        "table via `id_page_module`). Useful before editing a page or to understand a page's layout.",
      inputSchema: {
        id_page: z.number().int().positive(),
      },
    },
    async ({ id_page }) => {
      const [pagePayload, modulesPayload] = await Promise.all([
        api.get("pages", { linkTo: "id_page", equalTo: id_page }),
        api.get("modules", { linkTo: "id_page_module", equalTo: id_page }),
      ]);
      const page = (unwrapResults(pagePayload)[0] ?? null) as PageRow | null;
      if (!page) {
        throw new Error(`Page with id_page=${id_page} not found.`);
      }
      const modules = (unwrapResults(modulesPayload) as ModuleRow[]).map((m) => ({
        id_module: m.id_module,
        title: m.title_module,
        type: m.type_module,
        suffix: m.suffix_module,
        width: m.width_module,
      }));
      return {
        content: [
          {
            type: "text",
            text: JSON.stringify(
              {
                page: {
                  id_page: page.id_page,
                  title: page.title_page,
                  url: page.url_page,
                  type: page.type_page,
                  parent: page.parent_page,
                  order: page.order_page,
                },
                module_count: modules.length,
                modules,
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
