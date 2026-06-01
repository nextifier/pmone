---
name: shaders
description: >-
  Use when building GPU/WebGPU visual effects with the `shaders` (shaders.com) library in a Nuxt/Vue frontend — shader backgrounds, hero effects, gradients, noise, blur/glass/distortion, animated textures, image/video effects, or the /shaders gallery, editor, and docs. Covers choosing a preset, wiring `<Shader>`/`<ShaderCanvas>`, composing/nesting components, requiresChild semantics, RTT/performance budgeting, SSR/ClientOnly, dynamic prop drivers, and exporting code/JPG. Fully offline — no shaders.com subscription/MCP needed.
metadata:
  type: project
---

# Shaders (offline)

`shaders` is a Vue/WebGPU component library: declarative GPU visual effects composed by nesting components inside a `<Shader>`. This skill bundles everything harvested from the shaders.com Pro subscription so it keeps working **without** a subscription. The installed npm package (`shaders` v2.5.x) ships the renderer + full prop registry; all curated presets and docs are committed alongside this skill.

Paths below are relative to the Nuxt app root (the folder holding `app/`).

## Absolute rule — telemetry

Every `<Shader>` MUST set `:disable-telemetry="true"`. Always render through `<ShaderCanvas>` (`app/components/shaders/ShaderCanvas.vue`) — it hard-codes that prop and the required `<ClientOnly>` wrapper. Never use the raw `<Shader>` directly in app code.

```vue
<ShaderCanvas :config="{ components: [{ type: 'LinearGradient', props: {} }] }" class="aspect-video w-full" />
```

For copy-paste code shown to users, generate it with `generateShaderCode()` (`app/components/shaders/generateShaderCode.js`) — it injects `:disable-telemetry="true"` and emits standard `<Shader>` markup.

## How effects compose (read `reference/guides/composing-effects.md`)

- One `<Shader>` per canvas. Never stack two. Layer by nesting children.
- Components evaluate **top-to-bottom** (like DOM). Later layers draw over earlier ones.
- Two kinds: **Generators** draw pixels (LinearGradient, Plasma, Aurora, Circle…) — cheap. **Effects** (`requiresChild: true`) transform pixels (Blur, Glass, Dither, FilmGrain…).
- An Effect with **explicit children** applies only to them. With **no children**, it falls back to all preceding siblings — the idiomatic way to stylize a whole composition (e.g. trailing `<FilmGrain/>`).
- `requiresChild` per component is in `reference/component-index.md` (and `app/components/shaders-docs/data/registry/<Name>.json`).

## Performance (read `reference/guides/performance.md`)

- Generators ≈ free. **Filter/Effect components need a render-to-texture (RTT) pass** — Blur, Glass, GlassTiles, Distortions, the `map` prop driver, non-default transforms, mask sources. Each RTT boundary costs; avoid deep nesting of heavy filters (1–2 is fine).
- Use `visible: false` (not `opacity: 0`) to truly exclude a layer.

## SSR / Nuxt setup (important)

WebGPU is client-only. `<ShaderCanvas>` already wraps `<ClientOnly>` and **dynamic-imports** `shaders/vue`. The `/shaders/editor` and `/shaders/docs/**` routes are `ssr: false`.

Build gotchas that must stay in place (otherwise the dev server throws "Maximum call stack size exceeded"):
- `nuxt.config` → `vite.optimizeDeps.exclude: ["shaders", "shaders/vue", "shaders/registry", "shaders/vue/codegen"]`. Pre-bundling `shaders/vue` into one giant file overflows a Vite plugin's regex. Do NOT add `shaders` to `build.transpile`.
- Never **static-import** `shaders/vue` (it runs during SSR + eager transform) — only dynamic, client-side.
- Never import the big `shaders/registry` or `shaders/vue/codegen` modules in the browser. Use the extracted per-component JSON in `app/components/shaders-docs/data/registry/` (via `useShaderRegistry()`) and the local `generateShaderCode.js`.

## Where things live

- Routes: gallery `/shaders` · editor `/shaders/editor` · docs `/shaders/docs`
- Runtime: `app/components/shaders/` (`ShaderCanvas`, `ShaderTree`, `ShaderControls`, `generateShaderCode.js`)
- Composables: `useShaderRegistry()` (prop schemas), `useShaderExport()` (captureImage→JPG)
- Data (committed, offline): `app/components/shaders-docs/data/` — `presets/<id>.json` (parsed component config), `presets-index.json`, `collections/index.json`, `registry/<Name>.json`
- Thumbnails: `public/shaders/thumbnails/<id>.webp`
- Harvest/build scripts: `scripts/shaders-harvest/`

## Reference (read on demand)

- `reference/component-index.md` — prop schema (type, default, range) for all 117 components, grouped by category. The authoritative prop source.
- `reference/component-directory.md` — categorized component list with roles.
- `reference/preset-catalog.md` — every curated preset's title, collection, and id (the full config is in `data/presets/<id>.json`).
- `reference/guides/*.md` — the Vue/Nuxt guides (composing, blending-masking, transforms, dynamic-props, color-space, performance, vue/ssr, telemetry, …).
- `reference/pro-notes/*.md` — expert patterns (hero-section-masking, interactions, dynamic-prop-mapping, finishing-touches, shape-effects-placement, media-effects, color-spaces, composition). Check these before hand-writing a shader.
- `reference/guidelines.md` — the original shaders.com authoring rules.

## Common tasks

- **Drop in a preset background:** find it in `reference/preset-catalog.md`, load `data/presets/<id>.json`, render `<ShaderCanvas :config="preset.config" :color-space :tone-mapping>`. Mask it into a hero via `reference/pro-notes/hero-section-masking.md` (in-shader masking, not CSS).
- **Build from scratch:** read `composing-effects.md` + relevant component entries in `component-index.md`, pick a generator base + optional effects, keep RTT passes low.
- **Customize visually:** open `/shaders/editor?preset=<id>`; export code (telemetry-disabled) or JPG.
