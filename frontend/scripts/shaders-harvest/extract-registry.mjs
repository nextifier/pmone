// Extract the shaders package's component prop registry into small, per-component
// JSON files we own. Importing the package's single huge `shaders/registry`
// module in the browser overflows a Vite plugin's regex code-filter; per-file
// JSON (loaded via import.meta.glob) sidesteps that AND keeps us independent of
// the package's internal registry format. Run in Node (no Vite involved).
import { writeFileSync, mkdirSync, rmSync, existsSync } from "node:fs";
import { dirname, resolve, join } from "node:path";
import { fileURLToPath } from "node:url";

const HERE = dirname(fileURLToPath(import.meta.url));
const FRONTEND = resolve(HERE, "../..");
const OUT_DIR = resolve(FRONTEND, "app/components/shaders-docs/data/registry");
const INDEX_FILE = resolve(FRONTEND, "app/components/shaders-docs/data/registry-index.json");

const { shaderRegistry } = await import(
  resolve(FRONTEND, "node_modules/shaders/dist/registry.js")
);

if (existsSync(OUT_DIR)) rmSync(OUT_DIR, { recursive: true, force: true });
mkdirSync(OUT_DIR, { recursive: true });

const index = [];
for (const entry of shaderRegistry) {
  const def = entry.definition ?? {};
  const record = {
    name: entry.name,
    category: entry.category,
    description: entry.description ?? def.description ?? "",
    requiresChild: Boolean(entry.requiresChild ?? def.requiresChild),
    requiresRTT: Boolean(def.requiresRTT),
    props: def.props ?? entry.propsMetadata ?? {},
  };
  writeFileSync(join(OUT_DIR, `${entry.name}.json`), JSON.stringify(record, null, 2));
  index.push({
    name: record.name,
    category: record.category,
    description: record.description,
    requiresChild: record.requiresChild,
    requiresRTT: record.requiresRTT,
    propCount: Object.keys(record.props).length,
  });
}

index.sort((a, b) => a.name.localeCompare(b.name));
writeFileSync(INDEX_FILE, JSON.stringify(index, null, 2));

console.log(`extracted ${index.length} component registry files -> ${OUT_DIR}`);
console.log(`wrote index -> ${INDEX_FILE}`);
const cats = [...new Set(index.map((c) => c.category))];
console.log(`categories (${cats.length}): ${cats.join(", ")}`);
