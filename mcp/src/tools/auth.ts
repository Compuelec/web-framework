import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import type { TokenStore } from "../auth/tokenStore.js";

export function registerAuthTools(server: McpServer, tokenStore: TokenStore): void {
  server.registerTool(
    "whoami",
    {
      title: "Check the current MCP session",
      description:
        "Reports whether the MCP server has an authenticated session with the framework. " +
        "Returns the logged-in email, JWT expiration, and how the session was obtained " +
        '("env" = automatic via FW_AUTH_* env vars, "interactive" = via mcp_login). ' +
        "Useful before attempting writes — those require a session.",
      inputSchema: {},
    },
    async () => {
      const status = await tokenStore.whoami();
      if (!status.authenticated && !tokenStore.hasCredentials()) {
        return {
          content: [
            {
              type: "text",
              text: JSON.stringify(
                {
                  authenticated: false,
                  reason:
                    "No active session and no FW_AUTH_EMAIL/FW_AUTH_PASSWORD configured. " +
                    "Run the `mcp_login` tool to authenticate interactively via the CMS window.",
                },
                null,
                2,
              ),
            },
          ],
        };
      }
      return {
        content: [{ type: "text", text: JSON.stringify(status, null, 2) }],
      };
    },
  );
}
