// Docs registry for /shaders/docs - globs the harvested markdown (guides,
// pro-notes, component docs) and exposes lookup + sidebar nav. Mirrors the
// ui-docs lookup/sidebar-nav split but unified here since all three doc types
// share the same loader.

const guideRaw = import.meta.glob("./guides/**/*.md", { eager: true, query: "?raw", import: "default" });
const proNoteRaw = import.meta.glob("./pro-notes/*.md", { eager: true, query: "?raw", import: "default" });
const componentRaw = import.meta.glob("./components/*.md", { eager: true, query: "?raw", import: "default" });

/** Split `--- frontmatter ---` from the body. Frontmatter is flat key: value. */
export function parseFrontmatter(raw) {
  const match = raw.match(/^---\r?\n([\s\S]*?)\r?\n---\r?\n?([\s\S]*)$/);
  if (!match) return { data: {}, body: raw };
  const data = {};
  for (const line of match[1].split(/\r?\n/)) {
    const idx = line.indexOf(":");
    if (idx === -1) continue;
    const key = line.slice(0, idx).trim();
    const value = line.slice(idx + 1).trim().replace(/^["']|["']$/g, "");
    data[key] = value;
  }
  return { data, body: match[2] };
}

function build(raw, dir, type) {
  const out = {};
  for (const [path, content] of Object.entries(raw)) {
    const slug = path.replace(new RegExp(`^\\./${dir}/`), "").replace(/\.md$/, "");
    const { data, body } = parseFrontmatter(content);
    out[slug] = { slug, type, title: data.title || slug, description: data.description || "", data, body };
  }
  return out;
}

const guides = build(guideRaw, "guides", "guide");
const proNotes = build(proNoteRaw, "pro-notes", "pro-note");
const components = build(componentRaw, "components", "component");

export function getDocsEntry(slug) {
  return guides[slug] ?? proNotes[slug] ?? components[slug] ?? null;
}

// Curated guide order (most useful first); anything unlisted falls to the end.
const GUIDE_ORDER = [
  "index", "quickstart", "vue/quickstart", "composing-effects", "blending-masking",
  "layout-positioning", "transforms", "props-reactivity", "dynamic-props", "hooks-events",
  "color-space", "shape-effects", "performance", "vue/ssr", "telemetry", "mcp",
];

function ordered(map, order) {
  const keys = Object.keys(map);
  const ranked = keys.slice().sort((a, b) => {
    const ia = order.indexOf(a);
    const ib = order.indexOf(b);
    if (ia !== -1 && ib !== -1) return ia - ib;
    if (ia !== -1) return -1;
    if (ib !== -1) return 1;
    return map[a].title.localeCompare(map[b].title);
  });
  return ranked.map((slug) => ({ name: slug, title: map[slug].title }));
}

export const sidebarNav = [
  { label: "Guides", items: ordered(guides, GUIDE_ORDER) },
  { label: "Pro Notes", items: ordered(proNotes, []) },
  { label: "Components", items: ordered(components, []) },
].filter((group) => group.items.length > 0);

export const flatNav = sidebarNav.flatMap((group) =>
  group.items.map((item) => ({ ...item, group: group.label })),
);
