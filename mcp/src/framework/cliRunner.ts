import { spawn } from "node:child_process";

export type CliResult = {
  exitCode: number;
  stdout: string;
  stderr: string;
  command: string;
};

export class CliRunner {
  constructor(
    private readonly phpCmd: string,
    private readonly repoRoot: string,
    private readonly timeoutMs: number,
  ) {}

  /**
   * Runs `<phpCmd> <repoRoot>/<scriptRelPath>` and feeds `configJson` on stdin.
   * The framework's tools/make-*.php scripts accept their config via stdin so
   * we never have to interpolate JSON into a shell argument (no quoting traps).
   */
  async run(scriptRelPath: string, configJson: string): Promise<CliResult> {
    const parts = this.phpCmd.split(/\s+/).filter((p) => p.length > 0);
    if (parts.length === 0) {
      throw new Error("phpCmd is empty.");
    }
    const cmd = parts[0]!;
    const baseArgs = parts.slice(1);
    const args = [...baseArgs, `${this.repoRoot}/${scriptRelPath}`];
    const command = `${cmd} ${args.join(" ")}`;

    return await new Promise<CliResult>((resolve, reject) => {
      const child = spawn(cmd, args, { stdio: ["pipe", "pipe", "pipe"] });
      let stdout = "";
      let stderr = "";
      child.stdout.on("data", (d) => (stdout += d.toString()));
      child.stderr.on("data", (d) => (stderr += d.toString()));

      const timer = setTimeout(() => {
        child.kill("SIGKILL");
        reject(new Error(`CLI timed out after ${this.timeoutMs}ms: ${command}`));
      }, this.timeoutMs);

      child.once("error", (err) => {
        clearTimeout(timer);
        reject(err);
      });
      child.once("close", (code) => {
        clearTimeout(timer);
        resolve({ exitCode: code ?? -1, stdout, stderr, command });
      });

      child.stdin.write(configJson);
      child.stdin.end();
    });
  }
}

/**
 * Attempts to parse the JSON body the framework's make-*.php scripts print on
 * success. The scripts emit either a single JSON object on stdout (success)
 * or a single JSON object on stderr (failure). We try both, in that order.
 */
export function parseCliJson(result: CliResult): unknown {
  const candidates = [result.stdout, result.stderr];
  for (const candidate of candidates) {
    const trimmed = candidate.trim();
    if (!trimmed) continue;
    try {
      return JSON.parse(trimmed);
    } catch {
      // Some PHP warnings may prepend the JSON; pick the first {...} substring.
      const match = trimmed.match(/\{[\s\S]*\}/);
      if (match) {
        try {
          return JSON.parse(match[0]);
        } catch {
          // fall through and try the next stream
        }
      }
    }
  }
  return null;
}
