// Normalize em/en/horizontal dashes -> "-" across all shaders content. The
// shaders.com docs (harvested verbatim) and visual descriptions use em-dashes
// heavily, which violates the project's "no em-dash" rule. Run this after any
// harvest. 1:1 character replacement keeps spacing intact: " — " -> " - ", and a
// bare "—" placeholder -> "-".
import { readFileSync, writeFileSync, readdirSync, statSync } from "node:fs";
import { dirname, resolve, join, extname } from "node:path";
import { fileURLToPath } from "node:url";

const HERE = dirname(fileURLToPath(import.meta.url));
const FRONTEND = resolve(HERE, "../..");

const ROOTS = [
  "app/components/shaders-docs",
  "app/components/shaders",
  "app/pages/shaders",
  "app/composables",
].map((p) => resolve(FRONTEND, p));

const EXTRA_FILES = [
  "app/components/ShadersHeader.vue",
  "app/components/ShadersSidebar.vue",
  "app/components/ShadersSearch.vue",
  "app/components/ShadersSidebarTrigger.vue",
].map((p) => resolve(FRONTEND, p));

const ALLOWED = new Set([".md", ".json", ".vue", ".js", ".ts"]);
const DASHES = /[—–―‒]/g; // em, en, horizontal bar, figure dash

function walk(dir, out) {
  let entries;
  try {
    entries = readdirSync(dir);
  } catch {
    return;
  }
  for (const name of entries) {
    if (name === "node_modules") continue;
    const full = join(dir, name);
    const st = statSync(full);
    if (st.isDirectory()) walk(full, out);
    else if (ALLOWED.has(extname(name))) out.push(full);
  }
}

const files = [];
for (const root of ROOTS) walk(root, files);
for (const f of EXTRA_FILES) files.push(f);

let changed = 0;
let total = 0;
for (const file of files) {
  let text;
  try {
    text = readFileSync(file, "utf8");
  } catch {
    continue;
  }
  const matches = text.match(DASHES);
  if (!matches) continue;
  // Only target the composables that are shader-related (avoid touching unrelated files).
  if (file.includes("/app/composables/") && !/useShader/.test(file)) continue;
  writeFileSync(file, text.replace(DASHES, "-"));
  changed++;
  total += matches.length;
  console.log(`  ${matches.length}\t${file.replace(FRONTEND + "/", "")}`);
}

console.log(`\nfixed ${total} dash chars across ${changed} files`);
