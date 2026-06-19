const DEFAULT_DENY_TABLES = ["admins", "activity_logs", "sessions", "tokens"];

export type AuthCredentials = {
  table: string;
  suffix: string;
  email: string;
  password: string;
};

export type Config = {
  apiBaseUrl: string;
  apiKey: string;
  denyTables: Set<string>;
  httpTimeoutMs: number;
  auth: AuthCredentials | null;
  callbackHost: string;
  openBrowserOnLogin: boolean;
  phpCmd: string | null;
  repoRoot: string | null;
  cliTimeoutMs: number;
};

export function loadConfig(): Config {
  const apiBaseUrl = (process.env.FW_API_BASE_URL ?? "").replace(/\/+$/, "");
  const apiKey = process.env.FW_API_KEY ?? "";

  if (!apiBaseUrl) {
    throw new Error("FW_API_BASE_URL is required (e.g. http://localhost/web-framework/api)");
  }
  if (!apiKey) {
    throw new Error("FW_API_KEY is required (matches api/config.php → api.key)");
  }

  const denyRaw = process.env.FW_DENY_TABLES;
  const denyTables = new Set(
    (denyRaw && denyRaw.trim().length > 0 ? denyRaw.split(",") : DEFAULT_DENY_TABLES)
      .map((t) => t.trim().toLowerCase())
      .filter(Boolean),
  );

  const parsedTimeout = Number(process.env.FW_HTTP_TIMEOUT_MS ?? 15000);
  const httpTimeoutMs = Number.isFinite(parsedTimeout) && parsedTimeout > 0 ? parsedTimeout : 15000;

  const email = process.env.FW_AUTH_EMAIL ?? "";
  const password = process.env.FW_AUTH_PASSWORD ?? "";
  const auth: AuthCredentials | null =
    email && password
      ? {
          table: process.env.FW_AUTH_TABLE ?? "admins",
          suffix: process.env.FW_AUTH_SUFFIX ?? "admin",
          email,
          password,
        }
      : null;

  // For `mcp_login`, the framework POSTs back to a loopback URL on this host.
  // When the framework runs inside Docker Desktop and the MCP runs on the host,
  // use `host.docker.internal`. Default works when both live on the same host.
  const callbackHost = process.env.FW_CALLBACK_HOST || "127.0.0.1";

  // When set to "1" / "true", the `mcp_login` tool spawns the OS's default
  // browser pointing at the CMS authorization URL instead of just returning it.
  // Convenient for local desktop use; skip it on servers/CI.
  const openBrowserRaw = (process.env.FW_OPEN_BROWSER ?? "").toLowerCase();
  const openBrowserOnLogin = openBrowserRaw === "1" || openBrowserRaw === "true";

  // Scaffolding tools (`create_section`, `create_page`) need to spawn the
  // framework's `tools/*.php` scripts. Both vars are optional at load time:
  // tools that need them surface a clear error when called without them.
  // - phpCmd: command (and any prefix) used to run PHP. Examples:
  //     "php"                                  (same host as MCP)
  //     "docker exec -i wf_web php"            (framework in Docker)
  //     "ssh server php"                       (framework on a remote host)
  // - repoRoot: path inside whatever environment phpCmd runs in, pointing at
  //   the framework checkout root (where /tools/*.php and /api live).
  const phpCmd = process.env.FW_PHP_CMD?.trim() || null;
  const repoRoot = process.env.FW_REPO_ROOT?.trim().replace(/\/+$/, "") || null;

  const parsedCliTimeout = Number(process.env.FW_CLI_TIMEOUT_MS ?? 30000);
  const cliTimeoutMs =
    Number.isFinite(parsedCliTimeout) && parsedCliTimeout > 0 ? parsedCliTimeout : 30000;

  return {
    apiBaseUrl,
    apiKey,
    denyTables,
    httpTimeoutMs,
    auth,
    callbackHost,
    openBrowserOnLogin,
    phpCmd,
    repoRoot,
    cliTimeoutMs,
  };
}
