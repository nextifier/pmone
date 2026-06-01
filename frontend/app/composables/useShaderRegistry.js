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
    return byName[name]?.props ?? {};
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
