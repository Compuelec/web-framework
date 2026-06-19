import { promises as fs } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";

const DOC_FILES = [
  "API.md",
  "ARQUITECTURA.md",
  "SEGURIDAD.md",
  "AGENTE-CREAR-PAGINAS.md",
  "CMS-Y-TABLAS.md",
  "GENERADOR-PAGINAS.md",
  "DESARROLLO.md",
  "INSTALACION.md",
  "MANUAL-USUARIO.md",
] as const;

function resolveDocsDir(): string {
  // dist/resources/docs.js → ../../../docs (mcp/dist/resources → web-framework/docs)
  const here = path.dirname(fileURLToPath(import.meta.url));
  return path.resolve(here, "..", "..", "..", "docs");
}

export function registerDocResources(server: McpServer): void {
  const docsDir = resolveDocsDir();

  for (const file of DOC_FILES) {
    const slug = file.replace(/\.md$/i, "");
    const uri = `framework://docs/${slug}`;
    server.registerResource(
      `doc-${slug.toLowerCase()}`,
      uri,
      {
        title: `Framework doc: ${slug}`,
        description: `Markdown documentation file docs/${file} from the web-framework repository.`,
        mimeType: "text/markdown",
      },
      async () => {
        const fullPath = path.join(docsDir, file);
        const text = await fs.readFile(fullPath, "utf8");
        return {
          contents: [{ uri, mimeType: "text/markdown", text }],
        };
      },
    );
  }
}
