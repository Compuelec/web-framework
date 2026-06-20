const DEFAULT_DENY_TABLES = ["admins", "activity_logs", "sessions", "tokens"];

export type Config = {
  apiBaseUrl: string;
  apiKey: string;
  denyTables: Set<string>;
  httpTimeoutMs: number;
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

  const callbackHost = process.env.FW_CALLBACK_HOST || "127.0.0.1";

  const openBrowserRaw = (process.env.FW_OPEN_BROWSER ?? "").toLowerCase();
  const openBrowserOnLogin = openBrowserRaw === "1" || openBrowserRaw === "true";

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
    callbackHost,
    openBrowserOnLogin,
    phpCmd,
    repoRoot,
    cliTimeoutMs,
  };
}
