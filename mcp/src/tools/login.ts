import { spawn } from "node:child_process";
import { platform } from "node:os";
import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import type { Config } from "../config.js";
import type { TokenStore } from "../auth/tokenStore.js";
import { startLoginListener, type LoginHandle } from "../auth/loginServer.js";

/**
 * Derives the CMS base URL from the configured API base URL. The convention is
 * `<host>/api` ↔ `<host>/cms`, so we swap the suffix. If the API URL does not
 * follow that pattern we fall back to the same host with `/cms`.
 */
function deriveCmsBaseUrl(apiBaseUrl: string): string {
  if (apiBaseUrl.endsWith("/api")) return apiBaseUrl.slice(0, -"/api".length) + "/cms";
  try {
    const u = new URL(apiBaseUrl);
    return `${u.origin}/cms`;
  } catch {
    return apiBaseUrl.replace(/\/api\/?$/, "") + "/cms";
  }
}

function openInBrowser(url: string): void {
  const p = platform();
  const cmd = p === "darwin" ? "open" : p === "win32" ? "cmd" : "xdg-open";
  const args = p === "win32" ? ["/c", "start", "", url] : [url];
  const child = spawn(cmd, args, { stdio: "ignore", detached: true });
  child.on("error", (e) => console.error("[web-framework-mcp] could not open browser:", e.message));
  child.unref();
}

export function registerLoginTool(
  server: McpServer,
  cfg: Config,
  tokenStore: TokenStore,
): void {
  // A single active handle at a time — re-running `mcp_login` cancels any
  // earlier listener and issues a fresh URL.
  let active: LoginHandle | null = null;

  server.registerTool(
    "mcp_login",
    {
      title: "Open the CMS login window for the MCP",
      description:
        "Interactive login. Starts a local loopback listener and returns a URL the user must " +
        "open in their browser. The CMS mcp-setup page captures the admin's current JWT and " +
        "POSTs it back to this server, which stores it in memory. After confirmation, all " +
        "write tools (create_record/update_record/delete_record) will work without restarting. " +
        "The URL is valid for 5 minutes. Calling this tool again invalidates the previous URL.",
      inputSchema: {},
    },
    async () => {
      if (active) {
        active.cancel();
        active = null;
      }

      const handle = await startLoginListener(tokenStore, cfg.callbackHost);
      active = handle;

      const cmsBase = deriveCmsBaseUrl(cfg.apiBaseUrl);
      const setupUrl =
        `${cmsBase}/mcp-setup.php?session=${encodeURIComponent(handle.sessionToken)}` +
        `&callback=${encodeURIComponent(handle.callbackUrl)}`;

      // Fire-and-forget: once the user confirms, the listener resolves and we
      // simply clear our reference. Errors (timeout, cancel) just clear too.
      handle.done.finally(() => {
        if (active === handle) active = null;
      }).catch(() => {});

      let browserOpened = false;
      if (cfg.openBrowserOnLogin) {
        openInBrowser(setupUrl);
        browserOpened = true;
      }

      console.error(
        `[web-framework-mcp] mcp_login: open ${setupUrl} (waiting up to 5 min for callback)`,
      );

      // Block until the user completes the CMS flow (or it times out / is
      // cancelled). When the listener resolves, the JWT is already stored in
      // the TokenStore — we just report success to the LLM so it knows the
      // session is ready without needing a follow-up `whoami`.
      try {
        await handle.done;
      } catch (e) {
        return {
          isError: true,
          content: [
            {
              type: "text",
              text: JSON.stringify(
                {
                  status: "failed",
                  reason: e instanceof Error ? e.message : String(e),
                  login_url: setupUrl,
                  browser_opened: browserOpened,
                  hint: "Reintentá `mcp_login` para generar una URL nueva.",
                },
                null,
                2,
              ),
            },
          ],
        };
      }

      const session = await tokenStore.whoami();
      return {
        content: [
          {
            type: "text",
            text: JSON.stringify(
              {
                status: "authenticated",
                email: session.email,
                expires_at: session.expires_at,
                browser_opened: browserOpened,
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
