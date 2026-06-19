import { createServer, type Server, type IncomingMessage, type ServerResponse } from "node:http";
import { randomBytes } from "node:crypto";
import type { TokenStore } from "./tokenStore.js";

const CALLBACK_PATH = "/mcp/callback";
const LOGIN_TIMEOUT_MS = 5 * 60 * 1000;

export type LoginHandle = {
  port: number;
  sessionToken: string;
  callbackUrl: string;
  expiresAt: number;
  done: Promise<void>;
  cancel: () => void;
};

/**
 * Boots a one-shot loopback HTTP listener that accepts the JWT POSTed back from
 * the CMS's mcp-setup window. The listener auto-shuts after one successful
 * delivery or after LOGIN_TIMEOUT_MS — never both runs concurrently and the
 * caller is expected to cancel any earlier handle before requesting a new one.
 */
export async function startLoginListener(
  tokenStore: TokenStore,
  callbackHost: string = "127.0.0.1",
): Promise<LoginHandle> {
  const sessionToken = randomBytes(16).toString("hex");
  let resolveDone: () => void = () => {};
  let rejectDone: (e: Error) => void = () => {};
  const done = new Promise<void>((res, rej) => {
    resolveDone = res;
    rejectDone = rej;
  });

  const server: Server = createServer((req, res) => handle(req, res));
  await new Promise<void>((res) => server.listen(0, "127.0.0.1", () => res()));
  const address = server.address();
  const port = typeof address === "object" && address ? address.port : 0;

  const timer = setTimeout(() => {
    rejectDone(new Error(`mcp_login timed out after ${LOGIN_TIMEOUT_MS / 1000}s`));
    server.close();
  }, LOGIN_TIMEOUT_MS);

  function shutdown(): void {
    clearTimeout(timer);
    server.close();
  }

  function send(res: ServerResponse, status: number, body: string): void {
    res.writeHead(status, { "Content-Type": "text/plain; charset=utf-8" });
    res.end(body);
  }

  async function handle(req: IncomingMessage, res: ServerResponse): Promise<void> {
    if (req.method !== "POST" || req.url !== CALLBACK_PATH) {
      return send(res, 404, "not found");
    }
    let raw = "";
    req.setEncoding("utf8");
    for await (const chunk of req) raw += chunk;

    let body: Record<string, unknown>;
    try {
      body = JSON.parse(raw);
    } catch {
      return send(res, 400, "invalid json");
    }
    if (body.session !== sessionToken) {
      return send(res, 403, "session mismatch");
    }
    const jwt = String(body.jwt ?? "");
    const expiresAt = Number(body.expires_at);
    const email = String(body.email ?? "");
    const table = typeof body.table === "string" && body.table ? body.table : "admins";
    const suffix = typeof body.suffix === "string" && body.suffix ? body.suffix : "admin";
    if (!jwt || !Number.isFinite(expiresAt) || expiresAt <= 0 || !email) {
      return send(res, 400, "missing fields");
    }
    tokenStore.setSession({ jwt, expiresAt, email, table, suffix });
    send(res, 200, "ok");
    shutdown();
    resolveDone();
  }

  return {
    port,
    sessionToken,
    callbackUrl: `http://${callbackHost}:${port}${CALLBACK_PATH}`,
    expiresAt: Date.now() + LOGIN_TIMEOUT_MS,
    done,
    cancel: () => {
      shutdown();
      rejectDone(new Error("mcp_login cancelled"));
    },
  };
}
