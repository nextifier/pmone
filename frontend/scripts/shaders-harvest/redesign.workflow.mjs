export const meta = {
  name: "shaders-redesign",
  description: "Refactor /shaders pages to reuse the EXACT /ui design patterns (layout, sidebar, header search, card-frame) + hugeicons everywhere",
  phases: [
    { title: "Docs layout" },
    { title: "Header & search" },
    { title: "Gallery" },
    { title: "Icons" },
    { title: "App nav" },
  ],
};

const ROOT = "/Users/nextifier/Herd/pmone/frontend";

const PRINCIPLES = `This is a CONSISTENCY redesign (apply the spirit of the redesign-existing-projects + impeccable skills): audit the existing /ui design system, REUSE it exactly, introduce no new visual language, match STYLE_GUIDE.md (tracking-tight/tighter, CSS-variable colors only, max font-semibold, no hover:scale on images/cards), and only use \`hugeicons:\` icons — never lucide or any other set. Read ${ROOT}/STYLE_GUIDE.md.`;

const ICON_MAP = `Icon replacement map (lucide -> hugeicons), use EXACTLY these:
- arrow-left -> hugeicons:arrow-left-01
- arrow-right -> hugeicons:arrow-right-01
- chevron-up -> hugeicons:arrow-up-01
- chevron-down -> hugeicons:arrow-down-01
- x -> hugeicons:cancel-01
- plus -> hugeicons:add-01
- loader-circle -> hugeicons:loading-03
- image-down -> hugeicons:image-download-02
- sliders-horizontal -> hugeicons:sliders-horizontal
- check -> hugeicons:tick-02
- code -> hugeicons:source-code
- search -> hugeicons:search-01`;

const thunks = [];

// ---- Agent 1: Docs layout (mirror /ui exactly) ----
thunks.push(() =>
  agent(
    `${PRINCIPLES}

Goal: make /shaders/docs use the SAME layout/sidebar/scrollspy pattern as /ui — do NOT keep the custom self-built docs layout or the custom ShadersToc.

Read these references first: ${ROOT}/app/layouts/ui.vue, ${ROOT}/app/components/UiSidebar.vue, ${ROOT}/app/pages/ui/[name].vue, ${ROOT}/app/components/ui/scroll-spy/ScrollSpy.vue, ${ROOT}/app/components/shaders-docs/docs.js (exports getDocsEntry, sidebarNav, flatNav), ${ROOT}/app/components/shaders-docs/MarkdownDoc.vue, and the current ${ROOT}/app/pages/shaders/docs/[...slug].vue.

Tasks:
1. CREATE ${ROOT}/app/layouts/shaders.vue — clone app/layouts/ui.vue exactly, but render \`<ShadersSidebar :current-name="currentName" />\` and \`<ShadersHeader sidebar />\`. (ShadersHeader is built by a sibling agent; it takes a boolean \`sidebar\` prop and renders the sidebar trigger when true — just use \`<ShadersHeader sidebar />\`.) Keep SidebarProvider --sidebar-width 18rem, SidebarInset, the min-h-screen-offset + container-wider wrappers, and <slot/>. currentName = the docs slug: \`const route = useRoute(); const currentName = computed(() => { const s = route.params.slug; return Array.isArray(s) ? s.join('/') : (s || ''); });\`

2. CREATE ${ROOT}/app/components/ShadersSidebar.vue — clone app/components/UiSidebar.vue exactly, but: import \`sidebarNav\` from "@/components/shaders-docs/docs"; the SidebarHeader logo links to "/shaders" and shows the squircle (bg-sidebar-primary) with \`<Icon name="hugeicons:paint-board" class="text-primary-foreground size-5" />\`, label "Shaders", subtitle "Guides, components & presets"; each item links to \`/shaders/docs/\${item.name}\`; keep the currentName prop + active highlight (currentName === item.name), \`useSidebar().setOpenMobile\`, and \`useSidebarAutoScroll()\`. hugeicons only.

3. REWRITE ${ROOT}/app/pages/shaders/docs/[...slug].vue — set \`definePageMeta({ layout: "shaders" })\`. Mirror the layout of app/pages/ui/[name].vue: a \`<div class="min-w-0 flex-1">\` with a \`<DocsNotFound v-if="!entry" />\` (import from "@/components/ui-docs/DocsNotFound.vue") fallback, else \`<div class="relative flex items-start gap-x-4">\` containing:
   - \`<main class="mt-6 mb-24 min-w-0 flex-1 sm:p-10 sm:pb-32">\` with a \`<div class="mx-auto max-w-3xl">\` header: \`<h1 class="text-primary text-3xl font-semibold tracking-tighter sm:text-4xl lg:text-[2.5rem]">{{ entry.title }}</h1>\` + (if entry.description) a \`<p class="text-muted-foreground mt-3 text-base tracking-tight text-pretty sm:text-lg">{{ entry.description }}</p>\`. Then a content container \`<div :id="contentId" class="mx-auto mt-10 max-w-3xl scroll-mt-24">\` holding \`<MarkdownDoc :source="bodyWithoutTitle" :component-name="componentName" />\` (import MarkdownDoc from "@/components/shaders-docs/MarkdownDoc.vue"). Then a prev/next row (reuse the current file's prev/next logic via flatNav) using \`hugeicons:arrow-left-01\` / \`hugeicons:arrow-right-01\`.
   - \`<aside class="sticky top-(--navbar-height-desktop) hidden h-[calc(100vh-var(--navbar-height-desktop))] w-55 shrink-0 overflow-y-auto py-8 xl:block">\` with \`<ScrollSpy :key="contentId" :content-selector="\\\`#\${contentId}\\\`" exclude-selector="[role=tabpanel]" />\` (import ScrollSpy from "@/components/ui/scroll-spy/ScrollSpy.vue").
   contentId = the constant string "shaders-doc-content". \`bodyWithoutTitle\` = a computed that strips a single leading markdown H1 line (\`/^#\\s+.*$/m\` first occurrence) from entry.body so the title isn't duplicated. Keep the existing getDocsEntry / componentName / prev / next / usePageMeta logic. The page must NOT render its own header (the shaders layout already renders ShadersHeader).

4. DELETE the now-unused custom TOC: run \`rm -f ${ROOT}/app/components/shaders-docs/ShadersToc.vue\`.

Use the Edit/Write tools. Return a one-line list of files changed.`,
    { phase: "Docs layout", label: "docs layout + sidebar" },
  ),
);

// ---- Agent 2: Header + preset search ----
thunks.push(() =>
  agent(
    `${PRINCIPLES}

Goal: build the shared Shaders header with a preset search combobox, mirroring /ui's header + UiSearch.

Read references: ${ROOT}/app/components/UiHeader.vue, ${ROOT}/app/components/UiSearch.vue, ${ROOT}/app/components/shaders-docs/useShaderPresets.js (useShaderPresets() exposes \`index\` = array of {id,title,collectionId,category} and \`collectionsById\` Map), and the current ${ROOT}/app/components/ShadersHeader.vue.

Tasks:
1. CREATE ${ROOT}/app/components/ShadersSearch.vue — clone app/components/UiSearch.vue, but search PRESETS. Trigger button shows \`<Icon name="hugeicons:search-01" .../>\` + "Search presets" + the Cmd+K Kbd. Use \`const { index, collectionsById } = useShaderPresets()\` from "@/components/shaders-docs/useShaderPresets". CommandDialog with CommandInput placeholder "Search presets...", one \`<CommandGroup heading="Presets">\` with \`<CommandItem v-for="preset in index" :key="preset.id" :value="preset.title" @select="go(preset.id)">\` rendering \`<Icon name="hugeicons:arrow-right-02" class="mr-2 size-4" />\` + the title (+ optionally a muted collection name from collectionsById). \`go(id)\` does \`await router.push('/shaders/'+id); open.value=false\`. Keep \`defineShortcuts({ meta_k: { handler: () => open.value = !open.value } })\`. (760 items is fine — cmdk filters.)

2. CREATE ${ROOT}/app/components/ShadersSidebarTrigger.vue — a small component that calls \`const { toggleSidebar, open, isMobile } = useSidebar()\` (from "@/components/ui/sidebar/utils") and \`const { metaSymbol } = useShortcuts()\`, and renders the trigger button EXACTLY like the one in UiHeader (the Tippy wrapper + \`<button data-sidebar="trigger" data-slot="sidebar-trigger" class="text-primary hover:bg-muted flex size-8 items-center justify-center rounded-lg" @click="toggleSidebar">\` with \`<Icon v-if="open && !isMobile" name="hugeicons:sidebar-left-01" class="text-primary size-5" />\` else \`hugeicons:sidebar-left\`, and the tooltip content "Toggle Sidebar" + kbd "{{ metaSymbol }} B"). Keeping this in its own component means it only mounts inside a SidebarProvider.

3. REWRITE ${ROOT}/app/components/ShadersHeader.vue — sticky header mirroring UiHeader's shell classes (\`border-border/50 bg-background sticky inset-x-0 top-0 z-50 h-(--navbar-height-mobile) lg:h-(--navbar-height-desktop) border-b\`, inner \`flex h-full items-center gap-x-2 px-4\`). Props: \`{ sidebar: { type: Boolean, default: false } }\`. Left: \`<ShadersSidebarTrigger v-if="sidebar" />\`; \`<NuxtLink v-else to="/shaders" ...>\` the "Shaders" logo (squircle bg-sidebar-primary + \`<Icon name="hugeicons:paint-board" class="size-4" />\` + "Shaders" label). Then nav ghost Buttons (hidden below sm via \`hidden sm:flex\`): Gallery -> /shaders, Docs -> /shaders/docs, Editor -> /shaders/editor. Right side wrapper \`<div class="ml-auto flex grow items-center justify-end gap-x-1 sm:gap-x-2">\`: \`<ShadersSearch class="grow" />\` then \`<ColorModeToggle />\` (import { ColorModeToggle } from "@/components/ui/color-mode-toggle"). hugeicons only.

Return a one-line list of files changed.`,
    { phase: "Header & search", label: "header + preset search" },
  ),
);

// ---- Agent 3: Gallery card-frame ----
thunks.push(() =>
  agent(
    `${PRINCIPLES}

Goal: redesign the /shaders gallery cards to use the SAME card-frame pattern as /ui (frame header with title + description, shader image below), and move text search into the header combobox.

Read references: ${ROOT}/app/pages/ui/index.vue (study the card-frame markup — the \`<NuxtLink data-slot="card-frame">\` -> \`data-slot="card-frame-header"\` (h2 title + p description) -> \`data-slot="card"\` -> \`data-slot="card-panel"\` structure and ALL its exact classes), the current ${ROOT}/app/pages/shaders/index.vue and ${ROOT}/app/components/shaders/PresetCard.vue, and ${ROOT}/app/components/shaders-docs/useShaderPresets.js.

Tasks:
1. REWRITE ${ROOT}/app/components/shaders/PresetCard.vue — use the EXACT card-frame structure copied from app/pages/ui/index.vue's card (same data-slot attrs + same classes): the \`card-frame\` NuxtLink (to \`/shaders/\${preset.id}\`), the \`card-frame-header\` with \`<h2 class="self-center text-base font-semibold tracking-tight">{{ preset.title }}</h2>\` and \`<p class="text-muted-foreground line-clamp-2 self-center text-sm tracking-tight sm:h-[2lh]">{{ collectionName }}</p>\`, then the inner \`card\` + \`card-panel\`. In the panel, instead of the centered illustration, render the shader thumbnail filling the panel: a plain \`<img :src="preset.thumbnail" :alt="preset.title" loading="lazy" class="size-full rounded-lg object-cover" />\` — make the panel hold the image edge-to-edge (remove/zero the panel's inner padding and add overflow-hidden so the image fills nicely while keeping the frame header above it intact). Props: \`preset\` (Object, required), \`collectionName\` (String, default ""). Do NOT use NuxtImg and do NOT add any hover:scale.

2. REWRITE ${ROOT}/app/pages/shaders/index.vue — keep \`definePageMeta({ layout: "empty" })\`. Put \`<ShadersHeader />\` (gallery variant, no sidebar prop) as the very first element (it now contains the preset search combobox + color toggle). Keep the hero block (h1 "GPU shaders, ready to ship.", the description mentioning \`{{ index.length }}\` presets, and two Buttons: "Open editor" -> /shaders/editor with a trailing \`<Icon name="hugeicons:arrow-right-01" />\`, and "Read the docs" variant=outline -> /shaders/docs). REMOVE the inline text \`<Input>\` search entirely (search is in the header now). KEEP the category filter chip Buttons and the result count. Render the grid using the SAME classes as app/pages/ui/index.vue's grid (\`grid gap-3 pt-12 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4\`) of \`<PresetCard :preset="preset" :collection-name="collectionName(preset.collectionId)" />\`. Keep the existing useShaderPresets()/collectionsById/category-filter computed logic (drop only the query-string part). hugeicons only.

Return a one-line list of files changed.`,
    { phase: "Gallery", label: "gallery card-frame" },
  ),
);

// ---- Agent 4: Icon sweep (editor + detail + layer item) ----
thunks.push(() =>
  agent(
    `${PRINCIPLES}

Goal: replace EVERY lucide icon with its hugeicons equivalent in the editor/detail/layer files. Change ONLY the icon \`name\` strings — keep all other markup, classes, and behavior identical.

${ICON_MAP}

Edit these files:
- ${ROOT}/app/pages/shaders/editor.vue
- ${ROOT}/app/pages/shaders/[id].vue
- ${ROOT}/app/components/shaders/ShaderLayerItem.vue

After editing, run: \`grep -rn "lucide:" ${ROOT}/app/pages/shaders ${ROOT}/app/components/shaders ${ROOT}/app/components/shaders-docs ${ROOT}/app/components/Shaders*.vue 2>/dev/null\` and confirm it returns NOTHING. Return that grep output (must be empty).`,
    { phase: "Icons", label: "lucide -> hugeicons" },
  ),
);

// ---- Agent 5: App sidebar nav entry ----
thunks.push(() =>
  agent(
    `${PRINCIPLES}

Goal: add a "Shaders" entry to the main app sidebar nav, directly below the "UI Components" entry.

Read ${ROOT}/app/components/AppSidebarNavMain.vue. Find the nav item object for "UI Components" (\`label: "UI Components", path: "/ui"\`, around line 489 — it lives in an array of nav items). Insert a NEW sibling object IMMEDIATELY AFTER it, mirroring the EXACT shape/keys of that sibling object (whatever keys it uses — label, path, iconName, and any others; if the sibling has no permission gate, don't add one). Values for the new item: label "Shaders", path "/shaders", iconName "hugeicons:paint-board". Preserve formatting/indentation to match surrounding items.

Return the exact snippet you added.`,
    { phase: "App nav", label: "AppSidebarNavMain entry" },
  ),
);

log(`Redesign: ${thunks.length} agents over disjoint files (no conflicts), mirroring /ui patterns + hugeicons.`);
const results = await parallel(thunks);
return { agents: thunks.length, results };
