import registryIndex from "../components/shaders-docs/data/registry-index.json";

// We load the prop schema from our own extracted per-component JSON rather than
// the package's single huge `shaders/registry` module - that module overflows a
// Vite plugin's regex code-filter (see the shaders-vite-integration memory) and
// ties us to the package's internals. These files are generated offline by
// scripts/shaders-harvest/extract-registry.mjs.
const componentModules = import.meta.glob("../components/shaders-docs/data/registry/*.json", {
  eager: true,
  import: "default",
});

const byName = {};
for (const path in componentModules) {
  const record = componentModules[path];
  byName[record.name] = record;
}

// `shapeSdfUrl` ships from the extracted registry with no `ui` block (shaders.com
// only exposes it via their own editor), so it would otherwise fall through to a
// bare text input. Surface it as our upload control instead - applied here rather
// than edited into the generated JSON so a re-harvest can't clobber it.
const UI_OVERRIDES = {
  shapeSdfUrl: {
    ui: { type: "shape-upload", label: "Custom Shape", group: "Shape" },
  },
};

/**
 * Offline source of truth for shader component prop schemas (defaults, ranges,
 * control types, groups) - drives the visual editor controls and the docs prop
 * tables. Never touches the shaders.com MCP or subscription.
 */
export function useShaderRegistry() {
  const categories = [...new Set(registryIndex.map((c) => c.category))];

  const grouped = categories.map((category) => ({
    category,
    components: registryIndex.filter((c) => c.category === category),
  }));

  function getShaderByName(name) {
    return byName[name] ?? null;
  }

  function getShadersByCategory(category) {
    return registryIndex.filter((c) => c.category === category);
  }

  /** The `{ propName: { default, description, ui } }` map for a component. */
  function propsFor(name) {
    const raw = byName[name]?.props ?? {};
    let merged = raw;
    for (const key in UI_OVERRIDES) {
      if (key in raw && !raw[key].ui) {
        if (merged === raw) {
          merged = { ...raw };
        }
        merged[key] = { ...raw[key], ...UI_OVERRIDES[key] };
      }
    }
    return merged;
  }

  /** Effects (filters/distortions) require a child generator; generators do not. */
  function requiresChild(name) {
    return Boolean(byName[name]?.requiresChild);
  }

  return {
    index: registryIndex,
    byName,
    categories,
    grouped,
    getShaderByName,
    getShadersByCategory,
    propsFor,
    requiresChild,
  };
}
