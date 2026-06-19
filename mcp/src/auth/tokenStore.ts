import type { AuthCredentials } from "../config.js";
import type { FrameworkApiClient } from "../framework/apiClient.js";
import { unwrapResults } from "../utils/api.js";

type Session = {
  jwt: string;
  expiresAt: number; // epoch seconds
  email: string;
  table: string;
  suffix: string;
};

export type ExternalSession = Omit<Session, "table" | "suffix"> &
  Partial<Pick<Session, "table" | "suffix">>;

/**
 * Holds the JWT obtained from the framework's login endpoint and refreshes it
 * lazily on demand. Credentials never leave this object — the rest of the code
 * only sees the JWT and the auth query params for writes.
 *
 * Two ways to obtain a session:
 *  - From env-var credentials (FW_AUTH_EMAIL/FW_AUTH_PASSWORD) → automatic.
 *  - From the interactive `mcp_login` flow → caller invokes setSession() with
 *    the JWT POSTed back by the CMS's mcp-setup window. No password ever
 *    reaches the MCP server in that path.
 */
export class TokenStore {
  private session: Session | null = null;
  private loginInFlight: Promise<Session> | null = null;

  constructor(
    private readonly api: FrameworkApiClient,
    private readonly creds: AuthCredentials | null,
  ) {}

  hasCredentials(): boolean {
    return this.creds !== null;
  }

  async whoami(): Promise<{
    authenticated: boolean;
    email?: string;
    expires_at?: string;
    source?: "env" | "interactive";
  }> {
    if (!this.session) return { authenticated: false };
    return {
      authenticated: true,
      email: this.session.email,
      expires_at: new Date(this.session.expiresAt * 1000).toISOString(),
      source: this.creds ? "env" : "interactive",
    };
  }

  /**
   * Returns query-string params the framework expects on write endpoints:
   * `?token=<jwt>&table=<authTable>&suffix=<authSuffix>`. Triggers a login (or
   * refresh) if no valid session is cached.
   */
  async getAuthQuery(): Promise<Record<string, string>> {
    const session = await this.ensureSession();
    return {
      token: session.jwt,
      table: session.table,
      suffix: session.suffix,
    };
  }

  /** Force a re-login on the next call. Use when the framework returns 303/expired. */
  invalidate(): void {
    this.session = null;
  }

  /** Populate the session from an external source (used by the mcp_login flow). */
  setSession(input: ExternalSession): void {
    this.session = {
      jwt: input.jwt,
      expiresAt: input.expiresAt,
      email: input.email,
      table: input.table ?? this.creds?.table ?? "admins",
      suffix: input.suffix ?? this.creds?.suffix ?? "admin",
    };
  }

  async ensureSession(): Promise<Session> {
    const now = Math.floor(Date.now() / 1000);
    if (this.session && this.session.expiresAt > now + 30) {
      return this.session;
    }
    if (!this.creds) {
      throw new Error(
        "No active MCP session. Run the `mcp_login` tool to open the CMS authorization window, " +
          "or set FW_AUTH_EMAIL/FW_AUTH_PASSWORD so the server can log in automatically.",
      );
    }
    if (this.loginInFlight) return this.loginInFlight;
    this.loginInFlight = this.login(this.creds).finally(() => {
      this.loginInFlight = null;
    });
    return this.loginInFlight;
  }

  private async login(creds: AuthCredentials): Promise<Session> {
    const { table, suffix, email, password } = creds;
    const payload: Record<string, string> = {
      [`email_${suffix}`]: email,
      [`password_${suffix}`]: password,
    };
    const res = await this.api.post(table, payload, { login: "true", suffix });
    const rows = unwrapResults(res);
    const record = rows[0];
    if (!record) {
      throw new Error(`Login to ${table} as ${email} failed: framework returned no record.`);
    }
    const jwt = String(record[`token_${suffix}`] ?? "");
    const expRaw = record[`token_exp_${suffix}`];
    const expiresAt = typeof expRaw === "number" ? expRaw : Number(expRaw);
    if (!jwt || !Number.isFinite(expiresAt) || expiresAt <= 0) {
      throw new Error(
        `Login response did not include a valid token_${suffix}/token_exp_${suffix}.`,
      );
    }
    const session: Session = { jwt, expiresAt, email, table, suffix };
    this.session = session;
    return session;
  }
}
