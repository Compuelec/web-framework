#!/usr/bin/env node
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { loadConfig } from "./config.js";
import { FrameworkApiClient } from "./framework/apiClient.js";
import { registerTableTools } from "./tools/tables.js";
import { registerRecordTools } from "./tools/records.js";
import { registerPageTools } from "./tools/pages.js";
import { registerDocResources } from "./resources/docs.js";

async function main(): Promise<void> {
  const cfg = loadConfig();
  const api = new FrameworkApiClient(cfg);

  const server = new McpServer({
    name: "web-framework-mcp",
    version: "0.1.0",
  });

  registerTableTools(server, api, cfg);
  registerRecordTools(server, api, cfg);
  registerPageTools(server, api);
  registerDocResources(server);

  const transport = new StdioServerTransport();
  await server.connect(transport);
}

main().catch((err) => {
  // stdio is the protocol channel — log to stderr only.
  console.error("[web-framework-mcp] fatal:", err instanceof Error ? err.stack ?? err.message : err);
  process.exit(1);
});
