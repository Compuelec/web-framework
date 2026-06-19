import type { Config } from "../config.js";

export class FrameworkApiClient {
  constructor(private readonly cfg: Config) {}

  async get(table: string, query: Record<string, string | number | undefined> = {}): Promise<unknown> {
    const url = this.buildUrl(table, query);
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.cfg.httpTimeoutMs);
    try {
      const res = await fetch(url, {
        method: "GET",
        headers: {
          Authorization: this.cfg.apiKey,
          Accept: "application/json",
        },
        signal: controller.signal,
      });
      const text = await res.text();
      const body = parseJsonSafe(text);
      if (!res.ok) {
        throw new Error(`HTTP ${res.status} on GET ${url}: ${truncate(text, 200)}`);
      }
      return body;
    } catch (e) {
      if (e instanceof Error && e.name === "AbortError") {
        throw new Error(`Request to ${url} timed out after ${this.cfg.httpTimeoutMs}ms`);
      }
      throw e;
    } finally {
      clearTimeout(timer);
    }
  }

  private buildUrl(table: string, query: Record<string, string | number | undefined>): string {
    const usp = new URLSearchParams();
    for (const [k, v] of Object.entries(query)) {
      if (v !== undefined && v !== null && v !== "") usp.append(k, String(v));
    }
    const qs = usp.toString();
    return `${this.cfg.apiBaseUrl}/${encodeURIComponent(table)}${qs ? `?${qs}` : ""}`;
  }
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
