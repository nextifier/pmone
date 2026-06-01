import presetsIndex from "./data/presets-index.json";
import collections from "./data/collections/index.json";

// Per-id preset configs are loaded lazily - the gallery + search only need the
// compact index; the full ComponentConfig tree is fetched on the detail/editor
// page. Mirrors ui-docs/examples-loader.js (relative globs).
const presetModules = import.meta.glob("./data/presets/*.json");

export function useShaderPresets() {
  const collectionsById = new Map(collections.map((c) => [c.id, c]));

  async function getPreset(id) {
    const loader = presetModules[`./data/presets/${id}.json`];
    if (!loader) return null;
    const mod = await loader();
    return mod.default ?? mod;
  }

  /** Gallery groups: presets bucketed by collection, in the curated order. */
  function grouped() {
    const byCollection = new Map();
    for (const preset of presetsIndex) {
      if (!byCollection.has(preset.collectionId)) byCollection.set(preset.collectionId, []);
      byCollection.get(preset.collectionId).push(preset);
    }
    return collections
      .map((collection) => ({ collection, presets: byCollection.get(collection.id) ?? [] }))
      .filter((group) => group.presets.length > 0);
  }

  return { index: presetsIndex, collections, collectionsById, getPreset, grouped };
}
