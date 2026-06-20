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
import { registerScaffoldTools } from "./tools/scaffold.js";
import { registerDocResources } from "./resources/docs.js";

async function main(): Promise<void> {
  const cfg = loadConfig();
  const api = new FrameworkApiClient(cfg);
  const tokenStore = new TokenStore();

  const server = new McpServer({
    name: "web-framework-mcp",
    version: "0.3.0",
  });

  registerAuthTools(server, tokenStore);
  registerLoginTool(server, cfg, tokenStore);
  registerTableTools(server, api, cfg);
  registerRecordTools(server, api, cfg, tokenStore);
  registerPageTools(server, api);
  registerScaffoldTools(server, cfg, api);
  registerDocResources(server);

  console.error(
    "[web-framework-mcp] no active session — call `mcp_login` from the client to authorize the admin JWT.",
  );

  const transport = new StdioServerTransport();
  await server.connect(transport);
}

main().catch((err) => {
  // stdio is the protocol channel — log to stderr only.
  console.error("[web-framework-mcp] fatal:", err instanceof Error ? err.stack ?? err.message : err);
  process.exit(1);
});
