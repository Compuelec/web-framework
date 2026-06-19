#!/usr/bin/env node
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { loadConfig } from "./config.js";
import { FrameworkApiClient } from "./framework/apiClient.js";
import { TokenStore } from "./auth/tokenStore.js";
import { registerTableTools } from "./tools/tables.js";
import { registerRecordTools } from "./tools/records.js";
import { registerPageTools } from "./tools/pages.js";
import { registerAuthTools } from "./tools/auth.js";
import { registerLoginTool } from "./tools/login.js";
import { registerDocResources } from "./resources/docs.js";

async function main(): Promise<void> {
  const cfg = loadConfig();
  const api = new FrameworkApiClient(cfg);
  // The token store always exists — it just doesn't auto-login when env credentials
  // are missing. In that mode the user triggers `mcp_login` to populate it interactively.
  const tokenStore = new TokenStore(api, cfg.auth);

  const server = new McpServer({
    name: "web-framework-mcp",
    version: "0.2.0",
  });

  registerAuthTools(server, tokenStore);
  registerLoginTool(server, cfg, tokenStore);
  registerTableTools(server, api, cfg);
  registerRecordTools(server, api, cfg, tokenStore);
  registerPageTools(server, api);
  registerDocResources(server);

  // Eager-login so the first write doesn't pay the login latency and any credential
  // misconfiguration surfaces at startup instead of mid-conversation.
  if (cfg.auth) {
    try {
      await tokenStore.ensureSession();
      console.error("[web-framework-mcp] logged in as", cfg.auth.email);
    } catch (e) {
      console.error(
        "[web-framework-mcp] WARNING: eager login failed —",
        e instanceof Error ? e.message : e,
      );
      console.error("[web-framework-mcp] reads will still work; writes will retry on first call.");
    }
  } else {
    console.error(
      "[web-framework-mcp] no env credentials — call `mcp_login` from the client to authenticate interactively.",
    );
  }

  const transport = new StdioServerTransport();
  await server.connect(transport);
}

main().catch((err) => {
  // stdio is the protocol channel — log to stderr only.
  console.error("[web-framework-mcp] fatal:", err instanceof Error ? err.stack ?? err.message : err);
  process.exit(1);
});
