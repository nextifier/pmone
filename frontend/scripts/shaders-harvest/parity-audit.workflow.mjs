export const meta = {
  name: "shaders-parity-audit",
  description: "Audit feature/data/docs parity between shaders.com and PM One; write a gap report + return structured gaps",
  phases: [
    { title: "Catalog source + PM One" },
    { title: "Synthesize report" },
  ],
};

const ROOT = "/Users/nextifier/Herd/pmone/frontend";
const TMP = `${ROOT}/scripts/shaders-harvest/tmp/parity`;
const REPORT = `${ROOT}/scripts/shaders-harvest/parity-report.md`;

const SCOPE = `Scope decisions (already made by the user): OUT OF SCOPE = personal saved shaders + dashboard/account/auth/subscription (shaders.com SaaS), and semantic/vector search + find-similar (server embeddings). Search parity target = offline keyword + category + description only. Everything else (editor features, multi-framework export, docs, data) is IN SCOPE and must reach offline parity.`;

const GAP_SCHEMA = {
  type: "object",
  additionalProperties: false,
  properties: {
    summary: { type: "string", description: "2-4 sentence executive summary of parity status" },
    dataCompleteness: {
      type: "object",
      additionalProperties: false,
      description: "counts: shadersDotCom vs pmOne for each",
      properties: {
        presets: { type: "string" },
        components: { type: "string" },
        guides: { type: "string" },
        proNotes: { type: "string" },
        componentDocs: { type: "string" },
        collections: { type: "string" },
        notes: { type: "string", description: "any missing items found in the diff" },
      },
      required: ["presets", "components", "guides", "proNotes", "componentDocs", "collections", "notes"],
    },
    gaps: {
      type: "array",
      description: "Every IN-SCOPE feature/data gap (status partial or missing). Omit complete items and out-of-scope items.",
      items: {
        type: "object",
        additionalProperties: false,
        properties: {
          area: { type: "string", enum: ["editor", "export", "gallery", "docs", "data", "mcp"] },
          feature: { type: "string" },
          shadersCom: { type: "string", description: "what shaders.com offers" },
          pmOne: { type: "string", description: "current PM One state" },
          status: { type: "string", enum: ["partial", "missing"] },
          offlineFeasible: { type: "string", enum: ["yes", "partial", "no"] },
          packageSupport: { type: "string", description: "which shaders package API enables it offline, if any" },
          priority: { type: "string", enum: ["P0", "P1", "P2", "P3"] },
          recommendation: { type: "string" },
        },
        required: ["area", "feature", "shadersCom", "pmOne", "status", "offlineFeasible", "priority", "recommendation"],
      },
    },
    outOfScopeNoted: { type: "array", items: { type: "string" }, description: "shaders.com features intentionally excluded" },
  },
  required: ["summary", "dataCompleteness", "gaps", "outOfScopeNoted"],
};

// ---- Phase 1+2: four catalog agents in parallel (one browser, no conflict) ----
const catalogs = await parallel([
  // BROWSER — source of truth on shaders.com
  () =>
    agent(
      `Read-only BROWSER catalog of shaders.com (user is logged in Pro). Load Claude-in-Chrome tools via ToolSearch ("select:mcp__claude-in-chrome__tabs_context_mcp,mcp__claude-in-chrome__navigate,mcp__claude-in-chrome__computer,mcp__claude-in-chrome__get_page_text,mcp__claude-in-chrome__read_page"). Call tabs_context_mcp first, create a NEW tab, then navigate. Catalog FEATURES (controls/panels/options), not content. If a page fails after 2 tries, note it and move on — do NOT rabbit-hole; cap at a reasonable number of actions.

Catalog and WRITE findings to ${TMP}/browser.md (run mkdir -p ${TMP} first):
1. shaders.com/dashboard — what it offers (saved shaders, create, account, collections).
2. shaders.com/presets — search bar (semantic? suggested tags?), category sections + counts (Backgrounds/Logo Shaders/Image Effects/Recently Added), collections, filters/sort.
3. shaders.com/design-editor/4726121 — THE EDITOR, most important. Catalog every panel + control: layers panel (add/remove/reorder/group), properties (Colors; Effect with per-prop "Dynamic" toggle; Layer Transform: Offset X/Y, Rotation, Scale, Anchor), top bar (color space "P3 Linear", tone mapping, "Standard"), bottom toolbar icons (what categories they add), Export button (framework targets Vue/React/Svelte/Solid/JS + image JPG/PNG). Note masking, blend modes, opacity, visibility, undo/redo, save.
4. shaders.com/docs — Guides tab + Component Docs tab: list ALL guide titles + ALL component names + the framework switcher.

Return ONLY a 1-line status (e.g. "browser.md written, editor+gallery+docs cataloged, dashboard partial"). Put all detail in the file.`,
      { phase: "Catalog source + PM One", label: "browser: shaders.com", agentType: "Explore" },
    ),

  // MCP — source of truth counts + capabilities
  () =>
    agent(
      `Read-only. Enumerate the Shaders MCP (server "shaders") as a source-of-truth catalog. Load tools via ToolSearch ("select:mcp__shaders__get-shader-docs,mcp__shaders__list-collections"). Run mkdir -p ${TMP} first. Write to ${TMP}/mcp.md:
- Source counts: call get-shader-docs (no args) -> total component count + category list. Note the curated preset total (~760 across collections) and collection count (~130) from list-collections WITHOUT dumping the huge output (just the count fields).
- The full set of MCP tools and what each does, with an offline-replicable verdict (yes/partial/no): get-user-info, list-presets, get-preset, list-shaders, get-shader, get-shader-docs, search-presets, find-similar-presets, generate-sdf, list-collections, get-collection. (search/find-similar = embeddings = NO offline; saved-shaders/user-info = account = NO; generate-sdf = partial.)
- The MCP resource families: shaders://guidelines, component-directory, docs/guide/*, docs/components/*, pro-notes/*.
${SCOPE}
Return ONLY a 1-line status. Detail goes in the file.`,
      { phase: "Catalog source + PM One", label: "mcp: capabilities+counts" },
    ),

  // CODE — PM One feature inventory
  () =>
    agent(
      `Read-only inventory of PM One's /shaders implementation. Run mkdir -p ${TMP} first. Read ${ROOT}/app/pages/shaders/*, ${ROOT}/app/components/shaders/*, ${ROOT}/app/composables/useShader*. Write to ${TMP}/code.md an explicit "implemented vs MISSING" catalog of:
- Editor (editor.vue + ShaderControls/ShaderLayerItem/ColorPicker/PositionPad/generateShaderCode/useShaderExport): layer ops (add/remove/reorder/drag/select/nest/group/duplicate), per-prop control types supported, and which are MISSING: layer transform (offset/rotation/scale/anchor), blend modes, opacity/visible per layer, masking (maskSource/maskType), dynamic prop drivers ("map"/mouse/auto-animate — registry ui.type ["range","map"]), image/video/webcam texture source inputs, undo/redo, save/load custom shader.
- Export: which code frameworks (Vue only?) + image formats (jpg/png/webp).
- Gallery + search (index.vue + ShadersSearch.vue): browse/filter (category? collection browsing?), search (title only? category/description?).
Return ONLY a 1-line status. Detail in the file.`,
      { phase: "Catalog source + PM One", label: "code: PM One inventory", agentType: "Explore" },
    ),

  // DATA — PM One counts + diff readiness
  () =>
    agent(
      `Read-only. Count PM One's committed shaders data and write to ${TMP}/data.md (mkdir -p ${TMP} first). Report exact counts:
- presets: \`ls ${ROOT}/app/components/shaders-docs/data/presets/*.json | wc -l\` and entries in presets-index.json
- components: files in ${ROOT}/app/components/shaders-docs/data/registry/ + registry-index.json
- collections: entries in ${ROOT}/app/components/shaders-docs/data/collections/index.json
- guides: \`find ${ROOT}/app/components/shaders-docs/guides -name '*.md' | wc -l\` (incl vue/ subdir) + list the slugs
- pro-notes: files in ${ROOT}/app/components/shaders-docs/pro-notes/ + list slugs
- component docs: files in ${ROOT}/app/components/shaders-docs/components/ + count
- skill reference: list ${ROOT}/../.claude/skills/shaders/reference/ contents
Return ONLY a 1-line status with the headline counts.`,
      { phase: "Catalog source + PM One", label: "data: PM One counts" },
    ),
]);

log(`Catalogs done: ${catalogs.map((c) => (typeof c === "string" ? c : "?")).join(" | ")}`);

// ---- Phase 3: synthesis (reads the 4 catalog files, writes report, returns gaps) ----
const result = await agent(
  `You are the parity synthesizer. Read these four catalog files and produce the parity audit:
- ${TMP}/browser.md (shaders.com features — source of truth; may be partial if browser failed)
- ${TMP}/mcp.md (MCP capabilities + source counts)
- ${TMP}/code.md (PM One feature inventory)
- ${TMP}/data.md (PM One data counts)

Also use this verified background: the installed \`shaders\` package enables OFFLINE: TransformConfig (offsetX/Y, rotation, scale, anchorX/Y, edges), 19 BlendModes, MaskConfig (source + alpha/luminance/inverted), PropDriver dynamic modes (map, mouse-position, mouse, auto-animate), opacity/visible/renderOrder/id, codegen for Vue/React/Svelte/Solid/JS (shaders/{vue,react,svelte,solid,js}/codegen), captureImage png/jpeg/webp.

${SCOPE}

Tasks:
1. WRITE a clear human-readable markdown report to ${REPORT} containing: an executive summary; a data-completeness table (shaders.com vs PM One counts, flag any missing presets/components/docs); a PARITY MATRIX (feature | shaders.com | PM One | status | offline-feasible); a PRIORITIZED GAP ROADMAP (group by P0/P1/P2/P3 with a one-line recommendation each, and note which shaders package API enables each); and an "Out of scope" section.
2. RETURN the structured object per the schema (summary, dataCompleteness, gaps[], outOfScopeNoted[]). Only include IN-SCOPE gaps (status partial/missing); exclude complete items and out-of-scope items from the gaps array. Prioritize: P0 = core editor capabilities that block offline parity (dynamic props, transforms, blend, mask, multi-framework export); P1 = important (opacity/visible, nesting UI, texture inputs, collection browsing); P2/P3 = nice-to-have (duplicate, drag-reorder, undo/redo, search enhancements).`,
  { phase: "Synthesize report", label: "synthesize parity report", schema: GAP_SCHEMA },
);

return result;
