// Deterministic preset processor. Reads raw get-preset MCP responses written by
// the harvest agents (tmp/shaders-raw/presets/{id}.json), and produces the
// committed, offline-forever artifacts:
//   - app/components/shaders-docs/data/presets/{id}.json   (parsed config + meta)
//   - app/components/shaders-docs/data/presets-index.json  (compact gallery/search catalog)
//   - public/shaders/thumbnails/{id}.webp                   (downloaded + optimized)
//
// Idempotent: a thumbnail that already exists is not re-downloaded. Re-runnable
// after partial harvests.
import {
  readFileSync,
  writeFileSync,
  mkdirSync,
  readdirSync,
  existsSync,
  rmSync,
} from "node:fs";
import { dirname, resolve, join } from "node:path";
import { fileURLToPath } from "node:url";
import { tmpdir } from "node:os";
import { execFileSync } from "node:child_process";

const HERE = dirname(fileURLToPath(import.meta.url));
const FRONTEND = resolve(HERE, "../..");
const { parsePresetCode } = await import("./parse-preset.mjs");

const RAW_DIR = resolve(process.argv[2] ?? join(HERE, "tmp/shaders-raw/presets"));
const PRESETS_DIR = resolve(FRONTEND, "app/components/shaders-docs/data/presets");
const INDEX_FILE = resolve(FRONTEND, "app/components/shaders-docs/data/presets-index.json");
const THUMB_DIR = resolve(FRONTEND, "public/shaders/thumbnails");
const CONCURRENCY = 8;

mkdirSync(PRESETS_DIR, { recursive: true });
mkdirSync(THUMB_DIR, { recursive: true });

const rawFiles = existsSync(RAW_DIR)
  ? readdirSync(RAW_DIR).filter((f) => f.endsWith(".json"))
  : [];

if (!rawFiles.length) {
  console.error(`No raw preset files in ${RAW_DIR}`);
  process.exit(1);
}

async function downloadThumb(url, id) {
  const outWebp = join(THUMB_DIR, `${id}.webp`);
  if (existsSync(outWebp)) return { id, thumb: "skip" };
  if (!url) return { id, thumb: "no-url" };

  const res = await fetch(url);
  if (!res.ok) return { id, thumb: `http-${res.status}` };
  const buf = Buffer.from(await res.arrayBuffer());
  const tmpIn = join(tmpdir(), `shaders-${id}.jpg`);
  writeFileSync(tmpIn, buf);
  try {
    execFileSync("cwebp", ["-quiet", "-q", "80", "-resize", "720", "0", tmpIn, "-o", outWebp]);
  } finally {
    rmSync(tmpIn, { force: true });
  }
  return { id, thumb: "ok" };
}

const index = [];
const failures = [];
const thumbJobs = [];

for (const file of rawFiles) {
  const raw = JSON.parse(readFileSync(join(RAW_DIR, file), "utf8"));
  const id = raw.id ?? file.replace(/\.json$/, "");
  let parsed;
  try {
    parsed = parsePresetCode(raw.code);
  } catch (err) {
    failures.push({ id, error: String(err.message ?? err) });
    continue;
  }

  const preset = {
    id,
    title: raw.title ?? id,
    collectionId: raw.collection_id ?? null,
    category: raw.collection_category ?? null,
    description: raw.visual_description ?? "",
    thumbnail: `/shaders/thumbnails/${id}.webp`,
    config: { components: parsed.components },
    colorSpace: parsed.colorSpace ?? "p3-linear",
    toneMapping: parsed.toneMapping ?? "aces",
  };
  writeFileSync(join(PRESETS_DIR, `${id}.json`), JSON.stringify(preset, null, 2));

  index.push({
    id,
    title: preset.title,
    collectionId: preset.collectionId,
    category: preset.category,
    description: preset.description,
    thumbnail: preset.thumbnail,
  });

  thumbJobs.push({ url: raw.thumbnail_url, id });
}

// Download thumbnails with a small concurrency pool.
const counts = { ok: 0, skip: 0, fail: 0 };
let cursor = 0;
async function worker() {
  while (cursor < thumbJobs.length) {
    const job = thumbJobs[cursor++];
    try {
      const r = await downloadThumb(job.url, job.id);
      if (r.thumb === "ok") counts.ok++;
      else if (r.thumb === "skip") counts.skip++;
      else {
        counts.fail++;
        failures.push({ id: job.id, error: `thumb:${r.thumb}` });
      }
    } catch (err) {
      counts.fail++;
      failures.push({ id: job.id, error: `thumb:${String(err.message ?? err)}` });
    }
  }
}
await Promise.all(Array.from({ length: CONCURRENCY }, worker));

index.sort((a, b) => a.title.localeCompare(b.title));

// Merge with any existing index so partial re-runs accumulate.
let existing = [];
if (existsSync(INDEX_FILE)) {
  try {
    existing = JSON.parse(readFileSync(INDEX_FILE, "utf8"));
  } catch {
    existing = [];
  }
}
const byId = new Map(existing.map((e) => [e.id, e]));
for (const e of index) byId.set(e.id, e);
const merged = [...byId.values()].sort((a, b) => a.title.localeCompare(b.title));
writeFileSync(INDEX_FILE, JSON.stringify(merged, null, 2));

console.log(`presets processed: ${index.length}`);
console.log(`thumbnails: ok=${counts.ok} skip=${counts.skip} fail=${counts.fail}`);
console.log(`index total: ${merged.length}`);
if (failures.length) {
  console.log(`failures (${failures.length}):`);
  for (const f of failures.slice(0, 20)) console.log("  ", f.id, f.error);
  writeFileSync(join(HERE, "tmp/process-failures.json"), JSON.stringify(failures, null, 2));
}
