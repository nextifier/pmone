# UI Library docs

This folder powers `/ui` and `/ui/{name}` — a self-contained component documentation system that lives next to the components it documents. Drop a file, get a page. No index edits, no router config, no build step.

> **For Claude Code sessions**: this is the canonical guide. Read it once and you should be able to add any new component without re-reading the conversation that built this system.

---

## System overview

```
route                     /ui/{name}
   ↓
pages/ui/[name].vue   ←─── single dynamic page
   ↓
getDocsEntry(name)    ←─── lookup.js merges registry + guides
   ↓                          ↓
registry["button"]        guides["introduction"]
   ↓                          ↓
{ sections: [             { sections: [...] }
   { id, examples: [
     "default", "variants"
   ]}
] }
   ↓
getExample("button", "default")  ←─── examples-loader.js
   ↓
{
  component: <imported .vue>,
  source: "...?raw string..."
}
   ↓
<ComponentPreview :code><component /></ComponentPreview>
                ↓
        Tabs(Preview | Code)
                ↓
        CodeBlock (Shiki dual-theme + ButtonCopy)
```

Three glob sources keep everything in sync:

| Glob | Used by |
|---|---|
| `registry/*.js` | `registry/index.js` → `getEntry()` |
| `illustrations/*.vue` (except `IllustrationFrame.vue`) | `illustrations/index.js` → `getIllustration()` |
| `examples/*/*.vue` (both `default` and `?raw`) | `examples-loader.js` → `getExample()` |

The sidebar is **derived**: `sidebar-nav.js` reads `Object.keys(registry).sort()` and builds the "Components" group at render time. New entries appear automatically, alphabetical.

---

## Adding a component (cookbook)

To document `popover`:

### 1. Registry entry — `registry/popover.js`

```js
import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "popover",
  title: "Popover",
  description:
    "Floating panel anchored to a trigger. Built on reka-ui.",
  installation: {
    importPath: "@/components/ui/popover",
    imports: ["Popover", "PopoverTrigger", "PopoverContent"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Click the trigger to open the floating panel.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-form",
      title: "With form",
      description: "Common pattern: popover holds a quick edit form.",
      examples: ["with-form"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Popover",
      props: [
        { name: "open", type: "boolean", default: "—", description: "Open state. Supports v-model:open." },
        { name: "modal", type: "boolean", default: "false", description: "Trap focus and dim background when open." },
      ],
    },
    {
      component: "PopoverContent",
      props: [
        { name: "side", type: '"top" | "right" | "bottom" | "left"', default: '"bottom"', description: "Anchor side." },
        { name: "align", type: '"start" | "center" | "end"', default: '"center"', description: "Alignment along the side." },
      ],
    },
  ],
});
```

### 2. Examples — `examples/popover/default.vue` and `examples/popover/with-form.vue`

Each file mirrors the section ID. Use auto-imported globals when possible. Keep code self-explanatory.

```vue
<!-- examples/popover/default.vue -->
<script setup>
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Button } from "@/components/ui/button";
</script>

<template>
  <Popover>
    <PopoverTrigger as-child>
      <Button variant="outline">Open</Button>
    </PopoverTrigger>
    <PopoverContent class="w-64">
      <p class="text-sm tracking-tight">Anchored floating panel.</p>
    </PopoverContent>
  </Popover>
</template>
```

### 3. Illustration — `illustrations/popover.vue`

```vue
<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 flex-col gap-3 p-4">
      <div class="bg-muted-foreground/40 h-1.5 w-3/5 rounded-full" />
      <div class="bg-muted-foreground/8 h-3 rounded-sm" />
      <div class="bg-muted-foreground/8 h-3 w-4/5 rounded-sm" />
    </div>
  </IllustrationFrame>
</template>
```

Done. Open `/ui/popover` in the browser. Sidebar updated, search included, Preview/Code tabs work.

---

## Registry shape (`defineComponentDoc`)

`defineComponentDoc` is a pure identity function; its job is JSDoc hints in the IDE. See `registry/define.js` for the full type. Fields:

| Field | Required | Notes |
|---|---|---|
| `name` | yes | Kebab-case; must match the registry filename and the `examples/{name}/` folder. |
| `title` | yes | Display title. Fallback if omitted: `titleCase(name)`. |
| `description` | yes | One-line summary, shown on landing card + page header. Keep under 200 chars. |
| `installation.importPath` | optional | Shown in the (removed) Import card — keep populated; future versions may surface it. |
| `installation.imports` | optional | List of exported names from the index.ts. |
| `whenToUse` | optional | `{ title, description }`. Use for components with siblings (Dialog vs DialogResponsive, Sonner vs Notifications). Rendered as a section before the first example. |
| `anatomy` | optional | `{ tree: AnatomyNode[] }` where `AnatomyNode = { component: string, children?: AnatomyNode[] }`. `AnatomyDiagram.vue` turns the tree into an `import { … } from "<importPath>"` + nested `<template>` Vue code snippet (Shiki-highlighted, like reka-ui's Anatomy), rendered between `whenToUse` and the sections. Imports come from `installation.importPath`. Use for composite components (Card, Dialog, Form, Sidebar, ...). Leaf nodes omit `children`. Slot-only components use slot labels as nodes (e.g. `"#trigger slot"` → renders `<template #trigger />`). |
| `sections[]` | yes | Ordered list. Each: `{ id, title, description?, examples: string[], align?: "start"\|"center" }`. Aim for 3–7 sections on non-trivial components (default + variants/sizes/states/composition). Trivial ones (Spinner, Separator, Kbd, AspectRatio) may keep 1–2. |
| `sections[].examples` | yes | Array of filename stems matching `examples/{name}/{id}.vue`. One section can show multiple examples; render order is array order. |
| `sections[].align` | optional | `"start"` (default) for grids/tables/long content, `"center"` for compact previews (button, badge). |
| `apiReference[]` | optional but expected | One entry per sub-component. Each: `{ component, props?, events?, slots? }`. Props rows: `{ name, type, default, description }`. Events/slots rows: `{ name, description }`. |
| `accessibility` | optional | `{ keyboard: KeyBinding[], notes?: string[] }` where `KeyBinding = { keys: string[], description }`. Rendered by `AccessibilityTable.vue` as a Kbd-badge table + bullet notes, after API Reference. Add for interactive components (overlays, menus, listboxes, calendars, sliders, toggles). `keys` use short labels: `["Esc"]`, `["Shift","Tab"]`, `["↑"]`, `["Enter"]`. Base shortcuts on the underlying reka-ui/Radix primitive's documented keyboard behavior. |

---

## Example file rules

- One file per section ID. Filename uses kebab-case and matches the `examples` string in the section. So `examples: ["with-icon"]` → `examples/{name}/with-icon.vue`.
- Use `<script setup>` and explicit imports. Even though Nuxt auto-imports most components, **keep explicit imports** so the copied source code is self-documenting outside the project.
- **Generic content only — NO PM One / event / domain-specific context.** This is a reusable component library, not a PM One showcase. Banned in example data and copy: Indonesian/event brand names ("Air Minum Biru", "Burger Bangor", "Cheezy Coin", ...), exhibitor/event terms ("Booth", "Exhibitor", "Megabuild", category like "Food & Beverage"), hotel/reservation wording, Rupiah amounts, and any `*.id` project URLs. The only acceptable brand mention is "UI Library" (README/sidebar only).
- **When porting an example from `app/pages/demo/*`, swap the demo's PM One data for generic placeholders** — do not copy its arrays verbatim. Use shadcn-vue placeholder language: companies "Acme Inc" / "Globex Corp" / "Initech" / "Umbrella Co", people "Olivia Martin" / "Jackson Lee", "olivia@example.com", "INV-001", "Edit profile", and neutral labels ("Item 1", "Photo 1", "Members", "Projects"). A pricing/inventory component's built-in currency formatting (e.g. Rupiah) is fine to leave — only the example's own content must be generic.
- Avoid in-example logic that's not part of the demo. Refs, computed, or handlers should illustrate the component, not solve unrelated problems.
- Keep examples short. 10–30 lines is the sweet spot. If you need 60+ lines, split into multiple examples or simplify.

---

## Illustrations — design pattern

The landing-page card art mimics coss.com/ui. Each illustration is a tiny abstract mockup of the component using primitive shapes, not the real component. This keeps the page fast and the visual consistent.

### Primitive vocabulary

| Shape | Use for |
|---|---|
| `<div class="h-1.5 bg-muted-foreground/88 rounded-full" />` | Strong text line (heading) |
| `<div class="h-1.5 bg-muted-foreground/40 rounded-full" />` | Body text line |
| `<div class="h-1.5 bg-muted-foreground/20 rounded-full" />` | Secondary text line |
| `<div class="h-4 bg-muted-foreground/8 rounded-sm" />` | Paragraph block |
| `<div class="size-2 rounded-full bg-muted-foreground/88" />` | Dot/marker |
| `<div class="h-4 rounded-sm bg-linear-to-b from-(--btn-from) to-(--btn-to)" />` | Primary action highlight |
| `<Icon name="lucide:chevron-down" class="text-muted-foreground/88 size-4" />` | Inline affordance (chevron, search, x) |

### IllustrationFrame wrapper

Located at `illustrations/IllustrationFrame.vue`. Wrap every illustration unless the component is a composite that doesn't fit a single frame (e.g. `tabs.vue`).

```vue
<IllustrationFrame max-width="36" variant="gradient" radius="14px">
  <!-- content -->
</IllustrationFrame>
```

Props:

| Prop | Type | Default | Use when |
|---|---|---|---|
| `maxWidth` | `"24"` \| `"36"` \| `"50"` \| `"72"` | `"36"` | Smaller for compact components (badge), larger for full-width form controls (input). Tailwind JIT only knows these 4 values — don't pass others. |
| `variant` | `"gradient"` \| `"solid"` \| `"primary"` | `"gradient"` | `gradient` for surfaces (card, dialog, table); `solid` for form fields (input, select); `primary` for buttons. |
| `radius` | string CSS value | `"14px"` | Override for pills (`"9999px"`) or other shapes. |
| `overflow` | boolean | `false` | Set true when inner content has bleed (e.g. drawer handle). |

If you need a pattern that doesn't fit, **extend `IllustrationFrame`** (add a new variant) rather than copying its wrapper markup. Update this README when you do.

### Tips for designing illustrations

- Stay abstract: don't try to be photorealistic. The goal is "this looks like a Popover" at a glance.
- Match the component's silhouette: tall list → vertical bars; horizontal nav → row of trigger blocks.
- Use opacity levels (`/88`, `/40`, `/20`, `/8`) to create depth without color noise.
- Reference coss.com/ui inspect-element when stuck — most patterns translate directly.

---

## Style standards

These are non-negotiable since the docs are read across light/dark mode in multiple project contexts:

- **Language**: English only. No project-specific names.
- **Body text size**: `text-base sm:text-lg`. Never `text-sm` standalone for prose. `text-sm` is fine inside dense UI like API tables.
- **Headings**: `tracking-tighter` for h1/h2, `tracking-tight` for h3+. Max weight `font-semibold`.
- **Colors**: CSS variables only (`bg-card`, `text-muted-foreground`, `bg-muted-foreground/40`, etc.). No raw Tailwind palette (`bg-gray-700` etc.).
- **No em-dashes** (`—`) in copy. Use commas, periods, or parentheses.
- **No hover-scale** on cards or images.
- **Card grid gap**: `gap-3` on landing (tight, matches coss).

---

## Reusable subcomponents

| Component | Import | Purpose |
|---|---|---|
| `ComponentPreview` | `@/components/ui-docs/ComponentPreview.vue` | Wraps each example. Tabs(Preview \| Code) using segmented variant. |
| `CodeBlock` | `@/components/ui-docs/CodeBlock.vue` | Shiki dual-theme + line numbers + ButtonCopy. Used by ComponentPreview. |
| `ApiReferenceTable` | `@/components/ui-docs/ApiReferenceTable.vue` | One table per Props/Events/Slots. Props: `label`, `columns` (`[{ key, label, width?, mono?, monoSmall? }]`), `rows`. |
| `AnatomyDiagram` | `@/components/ui-docs/AnatomyDiagram.vue` | Renders the registry `anatomy.tree` as an import + nested `<template>` Vue code snippet via CodeBlock. Props: `tree` (AnatomyNode[]), `importPath`. |
| `AccessibilityTable` | `@/components/ui-docs/AccessibilityTable.vue` | Keyboard shortcut table (Kbd badges) + ARIA notes. Props: `keyboard` (KeyBinding[]), `notes` (string[]). Driven by the registry `accessibility` field. |
| `DocsPrevNext` | `@/components/ui-docs/DocsPrevNext.vue` | Bottom-of-page Button-based nav. |
| `DocsNotFound` | `@/components/ui-docs/DocsNotFound.vue` | Used when route param doesn't match registry. |
| `IllustrationFrame` | `@/components/ui-docs/illustrations/IllustrationFrame.vue` | Card-frame wrapper for illustrations. |

---

## Common pitfalls

- **Tabs needs `<TabsIndicator />`** inside `<TabsList>` for the sliding indicator to render. Easy to forget — every Tabs example in `examples/tabs/*.vue` must include it.
- **Reka-ui Tabs trigger uses `@mousedown.left`**, not `@click`. Programmatic click via tests/automation needs to dispatch a `mousedown` event first.
- **Tailwind JIT only sees static class strings.** Dynamic templates like `w-[${x}]` won't generate CSS. For variable widths use inline `style="width: ${x}"`. ApiReferenceTable already does this.
- **`?raw` query in glob.** `examples-loader.js` uses `{ eager: true, query: "?raw", import: "default" }` — both eager flag and `import: "default"` are required for Vite to inline the source string.
- **Example IDs match section IDs.** A section `{ id: "default", examples: ["default"] }` resolves `examples/{name}/default.vue`. Mismatch silently renders nothing.
- **Heavy components** (TipTapEditor, Sidebar, Chart) — don't try to fit everything in one example. Pick 2–4 realistic configurations and link to a usage file in the codebase for advanced cases via a `note` in the description.
- **introduction.vue illustration is intentionally absent**. The Introduction guide doesn't appear in the landing grid (filtered by `group === "Components"` in `pages/ui/index.vue`).
- **ScrollSpy `excludeSelector="[role=tabpanel]"`** is passed from `[name].vue` so headings inside Preview content don't pollute the right-rail TOC. Don't remove this prop.
- **CardNotch in a grid needs `items-start` on the container.** The notch is `position:absolute; bottom:0` of the card root. A grid's default `align-items: stretch` makes a short card's root taller than its body, so the notch detaches and floats below the content. Add `items-start` (or wrap each card in its own `<div>`) so each card sizes to its content.
- **Verify example data is accurate against the real component.** Several registries/examples shipped props that don't exist (e.g. `notchSize`/`notchGap`, `minDate`/`maxDate`, `prices`/`currency` on PricingCalendar) or wrong item keys (carousel/lightbox use `src`/`alt` and lightbox grid reads `sm`/`lg`, not a stray `image`). Read the component's `defineProps` + `index.ts` and check the live preview renders before trusting copied code.

---

## Heavy-component pattern

For components with significant surface area (tip-tap-editor, chart, sidebar, command, calendar):

1. **Default example**: smallest meaningful configuration (e.g. Chart with one line dataset, Sidebar with 3 nav items).
2. **Realistic example**: closer to how the codebase actually uses it (e.g. Chart with axis + legend + tooltip; Calendar with date range + disabled days).
3. **Sub-component map** in API ref: every sub-component gets an entry, even if only with a one-line description and the most useful 2–3 props. The reader can drill into the source if they need more.
4. **Pointer to in-repo usage** at the end of the description: "See `app/components/FormTask.vue` for the full toolbar configuration."

---

## File layout

```
components/ui-docs/
├── README.md                          ← you are here
├── ComponentPreview.vue               ← Tabs wrapper
├── CodeBlock.vue                      ← Shiki + ButtonCopy
├── ApiReferenceTable.vue              ← reusable table
├── DocsPrevNext.vue                   ← bottom nav
├── DocsNotFound.vue                   ← 404 state
├── examples-loader.js                 ← getExample(name, id)
├── lookup.js                          ← getDocsEntry(name)
├── sidebar-nav.js                     ← derived nav
├── examples/
│   └── {name}/
│       └── {sectionId}.vue
├── illustrations/
│   ├── IllustrationFrame.vue          ← shared wrapper
│   ├── index.js                       ← auto-glob
│   └── {name}.vue
├── registry/
│   ├── index.js                       ← auto-glob
│   ├── define.js                      ← defineComponentDoc + JSDoc
│   └── {name}.js
└── guides/
    ├── index.js
    └── introduction.js
```

Page entry points:
- `pages/ui/index.vue` — landing grid (uses `flatNav` + illustrations)
- `pages/ui/[name].vue` — single dynamic detail page

Surrounding chrome (only used by `[name].vue`):
- `components/UiSidebar.vue` — left nav, derived from registry
- `components/UiHeader.vue` — top bar with search + theme toggle
- `components/UiSearch.vue` — Cmd+K CommandDialog
