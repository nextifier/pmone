# PM One Shaders Implementation Inventory

## Status
**Editor: 4/12 core features implemented. Export: Vue + JPG only. Gallery: Category filter only (no search beyond title).**

---

## EDITOR (editor.vue + supporting components)

### Implemented
- **Layer Operations**
  - Add component (Popover picker with categorized components from registry)
  - Remove layer (by ID)
  - Reorder: Move up/down buttons (moveLayer with index delta)
  - Select/activate layer (activeId tracking, visual highlight)
  - Recursive children rendering (ShaderLayerItem with depth padding)
  
- **Per-Prop Control Types** (ShaderControls.vue / UI schema-driven)
  - `color` → ColorPicker (native swatch + text hex field)
  - `range` → Slider + number Input (min/max/step from ui.min/max/step)
  - `position` → PositionPad (2D draggable with x/y numeric inputs, 0..1 normalized)
  - `select` → shadcn Select dropdown
  - `checkbox` → shadcn Switch toggle
  - `text` → Text Input fallback
  - All props organized into accordion groups by ui.group

- **Canvas Rendering**
  - Real-time WebGPU shader preview via ShaderCanvas (dynamic import of shaders/vue)
  - Color space selector (p3-linear, srgb)
  - Tone mapping selector (linear, reinhard, cineon, aces, agx, neutral, hable, unreal)
  - Image download as JPEG (via useShaderExport.downloadImage)
  - Code copy-to-clipboard (generateShaderCode output)

### MISSING
- **Layer Transforms** (offset/rotation/scale/anchor)
- **Blend modes** (per-layer compositing)
- **Opacity/visible toggle** (per-layer)
- **Masking** (maskSource/maskType properties)
- **Dynamic prop drivers** (registry ui.type includes "map"—for mouse/auto-animate bindings—but no UI controls exist)
- **Texture sources** (image/video/webcam inputs for generators)
- **Undo/redo** (state history)
- **Save/load custom shader** (persistence; presets load only via ?preset query param)
- **Drag-to-reorder layers** (manual reorder UI exists, but not drag-drop)
- **Layer nesting/grouping** (children array structure exists in data model, but UI only shows flat hierarchy indentation—no explicit group component controls)
- **Duplicate layer** (shortcut to clone with new ID)

---

## EXPORT

### Implemented
- **Code Frameworks**
  - Vue only (generateShaderCode.js outputs SFC with <script setup> + <template>)
  - Always includes `:disable-telemetry="true"` (enforced)
  - Respects colorSpace and toneMapping as props

- **Image Formats**
  - JPEG (useShaderExport via ShaderCanvas.captureImage → download)
  - Download button in editor and preset detail page

### MISSING
- React, Svelte, Solid, vanilla JS code exports
- PNG, WebP, animated GIF exports
- Preset save/export (JSON config)
- Shader code as gist/Github

---

## GALLERY + SEARCH

### Implemented (index.vue / ShadersSearch.vue)
- **Browse**
  - Preset gallery grid (PresetCard with thumbnail, title, description)
  - Filter by category button group (activeCategory, computed filtered array)
  - Link to individual preset detail page (/shaders/[id].vue)
  - Collections metadata (collections.json with id/name)

- **Search**
  - Command dialog (Cmd/Ctrl+K) via ShadersSearch component
  - Searchable by **preset title only** (CommandInput filters by preset.title in index)
  - Shows collection label if it adds info (not just a repeat)
  - Navigate to preset detail on select

### MISSING
- Full-text search (title + description + category)
- Category-faceted search
- Collection browsing as explicit path
- Sort options (name, newest, trending)
- Advanced filters (resolution, performance, tags)
- Search history / saved searches
- Filter bar on gallery page (current implementation only has category tabs, no search)

---

## DATA LAYER

### Presets & Collections
- **useShaderPresets.js**
  - `presetsIndex` (compact array: title, id, category, description, collectionId, thumbnail)
  - `collections` (curated order, grouped() returns presets by collection)
  - Lazy-load full preset config (ComponentConfig tree) via getPreset(id)

### Component Registry
- **useShaderRegistry.js**
  - Loads per-component JSON schemas from `data/registry/*.json` (extracted offline)
  - propsFor(name) → { propName: { default, description, ui: { type, group, label, min, max, step, options } } }
  - requiresChild(name) detects if component needs children (generators vs effects)
  - Avoids loading package's huge shaders/registry module (Vite perf issue)

---

## FILE STRUCTURE
- Editor: `/app/pages/shaders/editor.vue`
- Gallery: `/app/pages/shaders/index.vue`
- Detail: `/app/pages/shaders/[id].vue`
- Canvas: `/app/components/shaders/ShaderCanvas.vue`
- Controls: `/app/components/shaders/ShaderControls.vue`
- Layer UI: `/app/components/shaders/ShaderLayerItem.vue`
- Code gen: `/app/components/shaders/generateShaderCode.js`
- Export: `/app/composables/useShaderExport.ts`
- Registry: `/app/composables/useShaderRegistry.js`
- Presets: `/app/components/shaders-docs/useShaderPresets.js`
- Search: `/app/components/ShadersSearch.vue`
- Header: `/app/components/ShadersHeader.vue`

