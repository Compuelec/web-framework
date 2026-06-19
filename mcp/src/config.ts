const DEFAULT_DENY_TABLES = ["admins", "activity_logs", "sessions", "tokens"];

export type Config = {
  apiBaseUrl: string;
  apiKey: string;
  denyTables: Set<string>;
  httpTimeoutMs: number;
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

  const httpTimeoutMs = Number(process.env.FW_HTTP_TIMEOUT_MS ?? 15000);

  return { apiBaseUrl, apiKey, denyTables, httpTimeoutMs };
}
