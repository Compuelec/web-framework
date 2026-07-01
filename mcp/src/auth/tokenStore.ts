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
 * Holds the JWT the admin authorized through `mcp_login`. The MCP never sees a
 * password — the CMS posts the admin's existing session JWT back to a loopback
 * callback, and we keep it in memory until it expires or the process dies.
 */
export class TokenStore {
  private session: Session | null = null;

  async whoami(): Promise<{
    authenticated: boolean;
    email?: string;
    expires_at?: string;
  }> {
    if (!this.session) return { authenticated: false };
    return {
      authenticated: true,
      email: this.session.email,
      expires_at: new Date(this.session.expiresAt * 1000).toISOString(),
    };
  }

  /**
   * Returns query-string params the framework expects on write endpoints:
   * `?token=<jwt>&table=<authTable>&suffix=<authSuffix>`. Throws if no session
   * has been authorized yet — caller must run `mcp_login` first.
   */
  async getAuthQuery(): Promise<Record<string, string>> {
    const session = this.ensureSession();
    return {
      token: session.jwt,
      table: session.table,
      suffix: session.suffix,
    };
  }

  /** Force the next write to fail until `mcp_login` runs again. */
  invalidate(): void {
    this.session = null;
  }

  /** Populate the session from the `mcp_login` callback. */
  setSession(input: ExternalSession): void {
    this.session = {
      jwt: input.jwt,
      expiresAt: input.expiresAt,
      email: input.email,
      table: input.table ?? "admins",
      suffix: input.suffix ?? "admin",
    };
  }

  private ensureSession(): Session {
    const now = Math.floor(Date.now() / 1000);
    if (!this.session || this.session.expiresAt <= now + 30) {
      throw new Error(
        "No active MCP session. Run the `mcp_login` tool to open the CMS authorization window.",
      );
    }
    return this.session;
  }
}
