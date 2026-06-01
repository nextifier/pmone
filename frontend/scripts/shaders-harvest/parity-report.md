# PM One `/shaders` vs shaders.com — Parity Audit

Generated 2026-06-01. Sources: `tmp/parity/{browser,mcp,code,data}.md`, verified against
live code (`app/pages/shaders/editor.vue`, `app/components/shaders/*`,
`app/composables/useShaderExport.ts`) and committed data
(`app/components/shaders-docs/data/*`).

---

## Executive summary

The **data and docs layers are at full parity**: 760 presets, 117 components, 130
collections, 15 guides, 8 pro-notes, and 117 component docs are all harvested and
committed offline — matching the shaders.com / MCP source counts exactly. The gap is
almost entirely in the **editor and export surfaces**. PM One's editor implements ~4 of
12 core capabilities (add/remove/reorder/select layers + per-prop controls + live
WebGPU canvas), but is missing every compositing primitive shaders.com exposes: layer
transforms, blend modes, per-layer opacity/visibility, masking, and dynamic prop drivers.
Export is Vue-only code + JPEG image, versus shaders.com's 5-framework code export and
multi-format image export.

Crucially, **every in-scope gap is offline-feasible today** — the installed `shaders`
package already ships the APIs needed (`TransformConfig`, 19 `BlendModes`, `MaskConfig`,
`PropDriver`, opacity/visible/renderOrder, per-framework `codegen`, `captureImage`
png/jpeg/webp). No server, subscription, or MCP call is required. These are purely
client-side UI wiring tasks. The out-of-scope surface (saved shaders, account/auth/
subscription, semantic/vector search) is correctly excluded.

---

## Data completeness

| Data type | shaders.com / MCP | PM One committed | Status |
|---|---:|---:|:--|
| Curated presets | 760 | 760 (`data/presets/*.json` + `presets-index.json`) | Complete |
| Components | 117 (9 cats) | 117 (`data/registry/*.json` + `registry-index.json`) | Complete |
| Component docs | 117 | 117 (`components/*`) | Complete |
| Collections | 130 (3 cats) | 130 (`data/collections/index.json`) | Complete |
| Guides | 15 + index | 15 + index (incl. `vue/quickstart`, `vue/ssr`) | Complete |
| Pro-notes | 8 | 8 | Complete |

No missing presets, components, collection entries, guides, pro-notes, or component docs
were found in the diff. The browser catalog under-counted components (107 across 8
categories) because the live docs UI omits the `Utilities` category and one Stylize
component; the MCP source-of-truth (117 / 9 categories) is authoritative, and PM One
matches it. Data layer requires no further work.

---

## Parity matrix

Offline-feasible legend: **Yes** = installed `shaders` package API enables it client-side;
**Partial** = reimplementable but not guaranteed bit-identical; **N/A** = out of scope.

### Editor

| Feature | shaders.com | PM One | Status | Offline |
|---|---|---|:--|:--|
| Add / remove layer | Yes | Yes (Popover picker + remove) | Complete | Yes |
| Reorder layers (buttons) | Yes | Yes (move up/down) | Complete | Yes |
| Select layer + property panel | Yes | Yes (activeId, schema-driven) | Complete | Yes |
| Per-prop controls (color/range/position/select/checkbox/text) | Yes | Yes (`ShaderControls.vue`) | Complete | Yes |
| Live preview canvas | Yes (WebGPU) | Yes (`ShaderCanvas` WebGPU) | Complete | Yes |
| Color space selector | P3 Linear / Linear / Standard | p3-linear / srgb | Complete | Yes |
| Tone mapping selector | (implicit) | 8 modes | Complete (exceeds) | Yes |
| **Layer transforms** (offsetX/Y, rotation, scale, anchorX/Y, edges) | Yes | **Missing** | Missing | Yes — `TransformConfig` |
| **Blend modes** (per-layer) | Yes | **Missing** | Missing | Yes — 19 `BlendModes` |
| **Opacity / visible toggle** | Yes | **Missing** | Missing | Yes — `opacity`/`visible` props |
| **Masking** (source + alpha/luminance/inverted) | Yes | **Missing** | Missing | Yes — `MaskConfig` |
| **Dynamic prop drivers** (map / mouse-position / mouse / auto-animate) | Yes ("Dynamic" toggle) | **Missing** (registry `ui.type:"map"` present, no UI) | Missing | Yes — `PropDriver` |
| **Texture inputs** (image / video / webcam) | Yes (DOM/Image/Video/Webcam textures) | **Missing** (components exist, no source-binding UI) | Missing | Yes — component props |
| **Layer nesting / grouping UI** | Yes (tree) | Partial (flat indentation, no group controls) | Partial | Yes — `requiresChild` |
| **Duplicate layer** | Yes | **Missing** | Missing | Yes — clone + new id |
| **Drag-to-reorder** | Yes | **Missing** (buttons only) | Missing | Yes — useSortable |
| **Undo / redo** | Yes (Cmd+Z) | **Missing** | Missing | Yes — state history |
| renderOrder / id control | Yes | Partial (id internal, no renderOrder UI) | Partial | Yes — `renderOrder`/`id` |
| Save / load custom shader | Yes (SaaS) | Out of scope | N/A | N/A |

### Export

| Feature | shaders.com | PM One | Status | Offline |
|---|---|---|:--|:--|
| Vue code | Yes | Yes (`generateShaderCode.js`, telemetry forced off) | Complete | Yes |
| **React / Svelte / Solid / JS code** | Yes (5 frameworks) | **Missing** (Vue only) | Missing | Yes — `shaders/{react,svelte,solid,js}/codegen` |
| JPEG image | Yes | Yes | Complete | Yes |
| **PNG / WebP image** | Yes (implied) | **Partial** (composable supports png/jpeg/webp; editor hardcodes jpeg) | Partial | Yes — `captureImage` already supports all 3 |
| Animated GIF | (not confirmed on .com) | Missing | Missing | Partial |
| Preset JSON export | (not confirmed) | Missing | Missing | Yes |

### Gallery + search

| Feature | shaders.com | PM One | Status | Offline |
|---|---|---|:--|:--|
| Preset grid + thumbnails | Yes | Yes (`PresetCard`) | Complete | Yes |
| Category filter | Yes | Yes (button group) | Complete | Yes |
| Preset detail page | Yes | Yes (`[id].vue`) | Complete | Yes |
| Keyword search | Yes (semantic) | Partial (title-only Cmd+K) | Partial | Yes — extend to title+desc+category |
| Category-faceted search | Yes | Missing | Missing | Yes |
| Collection browsing as explicit path | Yes (carousels per collection) | Partial (metadata only, no browse route) | Partial | Yes |
| Suggested tags / tag links | Yes | Missing | Missing | Yes (static tags) |
| Sort (name / newest / trending) | Yes | Missing | Missing | Partial (no trending offline) |
| Semantic / vector search + find-similar | Yes (embeddings) | Out of scope | N/A | N/A |

### Docs

| Feature | shaders.com | PM One | Status | Offline |
|---|---|---|:--|:--|
| Guides (15) | Yes | Yes (committed) | Complete | Yes |
| Component docs (117) | Yes | Yes (committed) | Complete | Yes |
| Pro-notes (8) | Yes | Yes (committed) | Complete | Yes |
| Framework selector in docs | Yes (5) | Depends on docs render layer | Partial | Yes |
| "Open in ChatGPT/Claude/..." actions | Yes | Out of scope (nice-to-have) | N/A | Partial |

---

## Prioritized gap roadmap

### P0 — Core editor capabilities blocking offline parity
All enabled by the installed `shaders` package; pure client-side UI wiring.

- **Layer transforms** — add a LAYER TRANSFORM accordion (offsetX/Y, rotation, scale,
  anchorX/Y, edges) writing to each node's `TransformConfig`.
- **Blend modes** — per-layer blend dropdown bound to the 19 `BlendModes` enum.
- **Masking** — mask source picker + alpha/luminance/inverted toggle → `MaskConfig`.
- **Dynamic prop drivers** — surface the existing registry `ui.type:"map"` as a "Dynamic"
  toggle per prop, emitting `PropDriver` (map / mouse-position / mouse / auto-animate).
- **Multi-framework code export** — wire `shaders/{react,svelte,solid,js}/codegen` behind
  a framework selector; keep the Vue path as-is (telemetry forced off).

### P1 — Important parity items

- **Opacity / visible toggle** — per-layer `opacity` slider + `visible` switch (package props).
- **Texture inputs** — image/video/webcam source binding for DOMTexture/ImageTexture/
  VideoTexture/WebcamTexture (component props).
- **Layer nesting / grouping UI** — explicit child-add for `requiresChild` components
  instead of flat indentation; expose `renderOrder`.
- **PNG / WebP image export** — add a format selector in the editor; `useShaderExport`
  and `captureImage` already support png/jpeg/webp, so this is a one-line UI exposure.
- **Collection browsing route** — render the 130 committed collections as a browsable
  path (carousel/grid), not just metadata for grouping.

### P2 — Nice-to-have editor ergonomics

- **Duplicate layer** — clone selected node tree with fresh ids (package `id` prop).
- **Drag-to-reorder** — `useSortable` over the existing layer tree (buttons stay as fallback).
- **Search enhancement** — extend Cmd+K to title + description + category + faceted filter
  (offline keyword target; semantic stays out of scope).
- **Suggested tags / static tag links** on the gallery.

### P3 — Polish

- **Undo / redo** — state-history stack (Cmd+Z / Cmd+Shift+Z).
- **Preset JSON export** — download the current ComponentConfig tree.
- **Sort options** (name / newest); trending is not derivable offline.
- **Docs framework selector** parity + AI-chat deep-link actions (optional).
- **generate-sdf** offline — algorithmic SDF for Glass/Neon/Crystal/Emboss/SmokeFill;
  partial (parity not guaranteed vs server output).

---

## Out of scope

These shaders.com features are intentionally excluded (user decision, confirmed by MCP catalog):

- **Personal saved shaders** + the dashboard grid of user creations.
- **Account / auth / subscription / Pro** surface (`get-user-info`, plan/billing).
- **Semantic / vector search** (`search-presets`) and **find-similar** (`find-similar-presets`)
  — both depend on server-side embeddings. Offline search target is keyword + category +
  description only.
- **Share-link / cloud persistence** (SaaS-hosted shader URLs).
