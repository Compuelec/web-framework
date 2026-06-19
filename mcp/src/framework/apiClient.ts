import type { Config } from "../config.js";

export type ScalarValue = string | number | boolean | null;

type Query = Record<string, string | number | undefined>;

export class FrameworkApiClient {
  constructor(private readonly cfg: Config) {}

  async get(table: string, query: Query = {}): Promise<unknown> {
    return this.request("GET", table, query);
  }

  async post(table: string, data: Record<string, ScalarValue>, query: Query = {}): Promise<unknown> {
    return this.request("POST", table, query, encodeForm(data));
  }

  async put(table: string, data: Record<string, ScalarValue>, query: Query = {}): Promise<unknown> {
    return this.request("PUT", table, query, encodeForm(data));
  }

  async delete(table: string, query: Query = {}): Promise<unknown> {
    return this.request("DELETE", table, query);
  }

  private async request(
    method: "GET" | "POST" | "PUT" | "DELETE",
    table: string,
    query: Query,
    body?: string,
  ): Promise<unknown> {
    const url = this.buildUrl(table, query);
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.cfg.httpTimeoutMs);
    try {
      const headers: Record<string, string> = {
        Authorization: this.cfg.apiKey,
        Accept: "application/json",
      };
      if (body !== undefined) {
        headers["Content-Type"] = "application/x-www-form-urlencoded";
      }
      const res = await fetch(url, { method, headers, body, signal: controller.signal });
      const text = await res.text();
      const parsed = parseJsonSafe(text);

      // GET on an empty result set returns 404 with body { status:404, results:"Not Found" }.
      // Swallow it as "no rows" only for reads — writes must surface 404 as a real error.
      if (method === "GET" && res.status === 404 && isFrameworkNotFoundBody(parsed)) {
        return { results: [] };
      }

      if (!res.ok) {
        throw new Error(`HTTP ${res.status} on ${method} ${url}: ${truncate(text, 200)}`);
      }
      return parsed;
    } catch (e) {
      if (e instanceof Error && e.name === "AbortError") {
        throw new Error(`Request to ${url} timed out after ${this.cfg.httpTimeoutMs}ms`);
      }
      throw e;
    } finally {
      clearTimeout(timer);
    }
  }

  private buildUrl(table: string, query: Query): string {
    const usp = new URLSearchParams();
    for (const [k, v] of Object.entries(query)) {
      if (v !== undefined && v !== null && v !== "") usp.append(k, String(v));
    }
    const qs = usp.toString();
    return `${this.cfg.apiBaseUrl}/${encodeURIComponent(table)}${qs ? `?${qs}` : ""}`;
  }
}

function encodeForm(data: Record<string, ScalarValue>): string {
  const usp = new URLSearchParams();
  for (const [k, v] of Object.entries(data)) {
    if (v === undefined) continue;
    if (v === null) {
      usp.append(k, "");
      continue;
    }
    usp.append(k, typeof v === "boolean" ? (v ? "1" : "0") : String(v));
  }
  return usp.toString();
}

function isFrameworkNotFoundBody(body: unknown): boolean {
  if (!body || typeof body !== "object") return false;
  const obj = body as Record<string, unknown>;
  return obj.status === 404 || obj.results === "Not Found";
}

function parseJsonSafe(text: string): unknown {
  if (!text) return null;
  try {
    return JSON.parse(text);
  } catch {
    return text;
  }
}

function truncate(s: string, n: number): string {
  return s.length > n ? `${s.slice(0, n)}…` : s;
}
