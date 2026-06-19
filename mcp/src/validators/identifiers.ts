import { z } from "zod";

const IDENTIFIER_RE = /^[a-z][a-z0-9_]*$/;

export const tableNameSchema = z
  .string()
  .min(1)
  .max(64)
  .regex(IDENTIFIER_RE, "Must be lowercase letters, digits or underscores, starting with a letter");

export const columnNameSchema = tableNameSchema;

export function isIdentifier(value: string): boolean {
  return IDENTIFIER_RE.test(value);
}

export function assertNotDenied(table: string, deny: Set<string>): void {
  if (deny.has(table.toLowerCase())) {
    throw new Error(`Table "${table}" is in the deny-list and cannot be accessed via this MCP server.`);
  }
}
