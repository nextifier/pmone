// Build data/collections/index.json + a flat preset-id list from the saved
// list-collections MCP dump (avoids a fresh 60KB MCP round-trip). The dump is a
// JSON object: { count, collections: [{ id, name, category, preset_count, presets:[uuid], tags?, notes? }] }.
import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const HERE = dirname(fileURLToPath(import.meta.url));
const FRONTEND = resolve(HERE, "../..");

const DUMP =
  process.argv[2] ??
  "/Users/nextifier/.claude/projects/-Users-nextifier-Herd-pmone/b5d54001-82ad-4c28-a80f-edb28ad88409/tool-results/mcp-shaders-list-collections-1780246481830.txt";

const OUT_COLLECTIONS = resolve(
  FRONTEND,
  "app/components/shaders-docs/data/collections/index.json",
);
const OUT_IDS = resolve(HERE, "tmp/preset-ids.json");

const raw = readFileSync(DUMP, "utf8");
const data = JSON.parse(raw);
const collections = data.collections ?? [];

const normalized = collections.map((c) => ({
  id: c.id,
  name: c.name,
  category: c.category ?? null,
  presetCount: c.preset_count ?? (c.presets ?? []).length,
  presetIds: c.presets ?? [],
  ...(c.tags ? { tags: c.tags } : {}),
  ...(c.notes ? { notes: c.notes } : {}),
}));

// Unique preset id list (presets can appear in multiple collections; keep first
// collection seen as the "primary" for grouping).
const idToCollection = new Map();
for (const c of normalized) {
  for (const pid of c.presetIds) {
    if (!idToCollection.has(pid)) idToCollection.set(pid, c.id);
  }
}
const ids = [...idToCollection.keys()];

mkdirSync(dirname(OUT_COLLECTIONS), { recursive: true });
mkdirSync(dirname(OUT_IDS), { recursive: true });
writeFileSync(OUT_COLLECTIONS, JSON.stringify(normalized, null, 2));
writeFileSync(
  OUT_IDS,
  JSON.stringify(
    ids.map((id) => ({ id, collectionId: idToCollection.get(id) })),
    null,
    2,
  ),
);

const totalRefs = normalized.reduce((n, c) => n + c.presetIds.length, 0);
console.log(`collections: ${normalized.length}`);
console.log(`preset refs (with dupes): ${totalRefs}`);
console.log(`unique preset ids: ${ids.length}`);
console.log(`wrote ${OUT_COLLECTIONS}`);
console.log(`wrote ${OUT_IDS}`);
