export type ApiRow = Record<string, unknown>;

export function unwrapResults(payload: unknown): ApiRow[] {
  let rawRows: unknown[] = [];
  if (Array.isArray(payload)) {
    rawRows = payload;
  } else if (payload && typeof payload === "object" && "results" in (payload as object)) {
    const r = (payload as { results: unknown }).results;
    rawRows = Array.isArray(r) ? r : [];
  }
  return rawRows.filter((item): item is ApiRow => typeof item === "object" && item !== null);
}
