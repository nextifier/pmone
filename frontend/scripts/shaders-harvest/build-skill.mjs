// Assemble the offline `shaders` skill: copy the harvested guides/pro-notes +
// MCP guidelines into the skill's reference/ dir, and generate a compact
// component prop index + preset catalog from our committed data. After this,
// the skill needs ZERO shaders.com MCP / subscription access.
import {
  readFileSync,
  writeFileSync,
  mkdirSync,
  rmSync,
  existsSync,
  readdirSync,
  copyFileSync,
} from "node:fs";
import { dirname, resolve, join } from "node:path";
import { fileURLToPath } from "node:url";

const HERE = dirname(fileURLToPath(import.meta.url));
const FRONTEND = resolve(HERE, "../..");
const PROJECT = resolve(FRONTEND, "..");
const SKILL = resolve(PROJECT, ".claude/skills/shaders");
const REF = resolve(SKILL, "reference");

const SD = resolve(FRONTEND, "app/components/shaders-docs");
const META = resolve(HERE, "tmp/meta");

function copyTree(srcDir, destDir) {
  if (!existsSync(srcDir)) return 0;
  mkdirSync(destDir, { recursive: true });
  let n = 0;
  for (const entry of readdirSync(srcDir, { withFileTypes: true })) {
    const src = join(srcDir, entry.name);
    const dest = join(destDir, entry.name);
    if (entry.isDirectory()) n += copyTree(src, dest);
    else if (entry.name.endsWith(".md")) {
      copyFileSync(src, dest);
      n++;
    }
  }
  return n;
}

mkdirSync(REF, { recursive: true });

// 1. Guides + pro-notes (the subscription-gated prose).
rmSync(join(REF, "guides"), { recursive: true, force: true });
rmSync(join(REF, "pro-notes"), { recursive: true, force: true });
const nGuides = copyTree(join(SD, "guides"), join(REF, "guides"));
const nNotes = copyTree(join(SD, "pro-notes"), join(REF, "pro-notes"));

// 2. MCP guidelines + component directory.
for (const f of ["guidelines.md", "component-directory.md"]) {
  if (existsSync(join(META, f))) copyFileSync(join(META, f), join(REF, f));
}

// 3. Component prop index from our extracted registry.
const regIndex = JSON.parse(readFileSync(join(SD, "data/registry-index.json"), "utf8"));
const byCategory = {};
for (const entry of regIndex) {
  const rec = JSON.parse(readFileSync(join(SD, `data/registry/${entry.name}.json`), "utf8"));
  const props = Object.entries(rec.props).map(([key, def]) => {
    const t = def.ui?.type;
    const type = Array.isArray(t) ? t.join("|") : (t ?? "?");
    let extra = "";
    if (def.ui?.min !== undefined) extra = `,${def.ui.min}-${def.ui.max}`;
    const dflt = typeof def.default === "object" ? JSON.stringify(def.default) : def.default;
    return `${key}:${type}(d=${dflt}${extra})`;
  });
  (byCategory[rec.category] ??= []).push(
    `- **${rec.name}** — ${rec.requiresChild ? "Effect (requiresChild)" : "Generator"}${rec.requiresRTT ? " [RTT]" : ""}\n  ${props.join(", ")}`,
  );
}
let compIndex = `# Shaders Component Index\n\nProp schema for all ${regIndex.length} components (source of truth: the installed \`shaders\` package, extracted offline). Type legend: color, range, position, select, checkbox, text, map (dynamic prop driver). \`d=\` is the default.\n`;
for (const [cat, items] of Object.entries(byCategory)) {
  compIndex += `\n## ${cat}\n\n${items.join("\n")}\n`;
}
writeFileSync(join(REF, "component-index.md"), compIndex);

// 4. Preset catalog from the gallery index.
const presets = JSON.parse(readFileSync(join(SD, "data/presets-index.json"), "utf8"));
const collections = JSON.parse(readFileSync(join(SD, "data/collections/index.json"), "utf8"));
const collName = new Map(collections.map((c) => [c.id, c.name]));
let catalog = `# Preset Catalog\n\n${presets.length} curated presets (browse live at /shaders). Each preset's full component config is in \`frontend/app/components/shaders-docs/data/presets/<id>.json\`.\n\n| Title | Collection | ID |\n| --- | --- | --- |\n`;
for (const p of presets) {
  catalog += `| ${p.title} | ${collName.get(p.collectionId) ?? "—"} | \`${p.id}\` |\n`;
}
writeFileSync(join(REF, "preset-catalog.md"), catalog);

console.log(`skill reference written to ${REF}`);
console.log(`  guides: ${nGuides}, pro-notes: ${nNotes}`);
console.log(`  component-index.md: ${regIndex.length} components`);
console.log(`  preset-catalog.md: ${presets.length} presets`);
