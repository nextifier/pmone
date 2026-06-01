export const meta = {
  name: "shaders-harvest",
  description: "Harvest all shaders.com presets + docs from the MCP into committed offline files",
  phases: [
    { title: "Presets" },
    { title: "Component docs" },
    { title: "Guides & Notes" },
  ],
};

const ROOT = "/Users/nextifier/Herd/pmone/frontend";
const IDS_FILE = `${ROOT}/scripts/shaders-harvest/tmp/preset-ids.json`;
const RAW_DIR = `${ROOT}/scripts/shaders-harvest/tmp/shaders-raw/presets`;
const META_DIR = `${ROOT}/scripts/shaders-harvest/tmp/meta`;
const GUIDES_DIR = `${ROOT}/app/components/shaders-docs/guides`;
const PRONOTES_DIR = `${ROOT}/app/components/shaders-docs/pro-notes`;
const COMPONENTS_DIR = `${ROOT}/app/components/shaders-docs/components`;

const COMPONENTS = ["AngularBlur","Ascii","Aurora","BarShift","Beam","Blob","Blur","BrickPattern","BrightnessContrast","Bulge","ChannelBlur","Checkerboard","Chevron","ChromaFlow","ChromaticAberration","Circle","ColorWheel","ConcentricSpin","ConicGradient","ContourLines","Crescent","Cross","CRTScreen","Crystal","CursorRipples","CursorTrail","DiamondGradient","DiffuseBlur","Dither","DOMTexture","DotGrid","DropShadow","Duotone","Ellipse","Emboss","FallingLines","FilmGrain","FloatingParticles","Flower","FlowField","FlowingGradient","FlutedGlass","Fog","Form3D","FractalNoise","Glass","GlassTiles","Glitch","Glow","Godrays","Grayscale","Grid","GridDistortion","Group","Halftone","HexGrid","HueShift","ImageTexture","Invert","Kaleidoscope","LensFlare","LinearBlur","LinearGradient","Liquify","Marble","Mirror","MultiPointGradient","Neon","Paper","Perspective","Pixelate","Plasma","PolarCoordinates","Polygon","Posterize","ProgressiveBlur","RadialGradient","RectangularCoordinates","ReflectivePlane","Ring","Ripples","RoundedRect","Saturation","Sharpness","Shatter","SimplexNoise","SineWave","Smoke","SmokeFill","Solarize","SolidColor","Spherize","Spiral","Star","Strands","Stretch","Stripes","StudioBackground","SunBurst","Swirl","TiltShift","Tint","Trapezoid","Tritone","Truchet","Twirl","Vesica","VHS","Vibrance","VideoTexture","Vignette","Voronoi","WaveDistortion","Weave","WebcamTexture","WorleyNoise","ZoomBlur"];

const GUIDE_SLUGS = ["composing-effects","blending-masking","color-space","dynamic-props","hooks-events","layout-positioning","performance","props-reactivity","shape-effects","telemetry","transforms","quickstart","vue/quickstart","vue/ssr","index","mcp"];
const PRONOTE_SLUGS = ["color-spaces","composition","dynamic-prop-mapping","finishing-touches","hero-section-masking","interactions","media-effects","shape-effects-placement"];

const TOTAL = 760;
const BATCH = 20;
const COMP_BATCH = 15;

function chunk(arr, size) {
  const out = [];
  for (let i = 0; i < arr.length; i += size) out.push(arr.slice(i, i + size));
  return out;
}

const presetBatches = [];
for (let offset = 0; offset < TOTAL; offset += BATCH) {
  presetBatches.push({ offset, limit: Math.min(BATCH, TOTAL - offset) });
}

const thunks = [];

// --- Presets ---
for (const { offset, limit } of presetBatches) {
  thunks.push(() =>
    agent(
      `You are a shaders.com harvest worker. Do NOT return file contents — only a short count.

1. Load the MCP tool: call ToolSearch with query exactly \`select:mcp__shaders__get-preset\`.
2. Read the JSON file ${IDS_FILE} — it is an array of {id, collectionId}. Take ONLY the slice from index ${offset} (inclusive) to ${offset + limit} (exclusive) — that is ${limit} ids.
3. Run \`mkdir -p ${RAW_DIR}\` once.
4. For each id in your slice:
   - If ${RAW_DIR}/<id>.json already exists, SKIP it.
   - Else call \`mcp__shaders__get-preset\` with { id: "<id>", format: "vue" }. On a transient error, retry once.
   - Write the FULL response object to ${RAW_DIR}/<id>.json as valid JSON. Keep every field verbatim — ESPECIALLY \`code\` and \`thumbnail_url\` — but you MAY drop the \`_instructions\` and \`_guidelines\` keys.
5. Return ONLY: "batch ${offset}: written=<n> skipped=<n> failed=[<ids>]". Under 60 words.`,
      { phase: "Presets", label: `presets ${offset}-${offset + limit}` },
    ),
  );
}

// --- Component docs ---
for (const names of chunk(COMPONENTS, COMP_BATCH)) {
  thunks.push(() =>
    agent(
      `You are a shaders.com docs harvest worker. Do NOT return file contents — only a short count.

1. Load the MCP tool: call ToolSearch with query exactly \`select:ReadMcpResourceTool\`.
2. Run \`mkdir -p ${COMPONENTS_DIR}\` once.
3. For each component name in this list: ${JSON.stringify(names)}
   - Target file: ${COMPONENTS_DIR}/<Name>.md . If it already exists, SKIP.
   - Else read the MCP resource: server "shaders", uri "shaders://docs/components/<Name>".
   - Write the resource's contents[0].text (the markdown, verbatim) to the target file.
4. Return ONLY: "components: written=<n> skipped=<n> failed=[<names>]". Under 50 words.`,
      { phase: "Component docs", label: `components ${names[0]}…` },
    ),
  );
}

// --- Guides ---
thunks.push(() =>
  agent(
    `You are a shaders.com docs harvest worker. Do NOT return file contents — only a short count.

1. Load the MCP tool: call ToolSearch with query exactly \`select:ReadMcpResourceTool\`.
2. Run \`mkdir -p ${GUIDES_DIR}/vue\` once.
3. For each guide slug in this list: ${JSON.stringify(GUIDE_SLUGS)}
   - Read MCP resource: server "shaders", uri "shaders://docs/guide/<slug>".
   - Write contents[0].text verbatim to ${GUIDES_DIR}/<slug>.md (note: slug "vue/quickstart" -> ${GUIDES_DIR}/vue/quickstart.md). Overwrite is fine.
   - If a resource read errors, skip and note it.
4. Return ONLY: "guides: written=<n> failed=[<slugs>]". Under 50 words.`,
    { phase: "Guides & Notes", label: "guides" },
  ),
);

// --- Pro notes ---
thunks.push(() =>
  agent(
    `You are a shaders.com docs harvest worker. Do NOT return file contents — only a short count.

1. Load the MCP tool: call ToolSearch with query exactly \`select:ReadMcpResourceTool\`.
2. Run \`mkdir -p ${PRONOTES_DIR}\` once.
3. For each pro-note slug in this list: ${JSON.stringify(PRONOTE_SLUGS)}
   - Read MCP resource: server "shaders", uri "shaders://pro-notes/<slug>".
   - Write contents[0].text verbatim to ${PRONOTES_DIR}/<slug>.md. Overwrite is fine.
4. Return ONLY: "pro-notes: written=<n> failed=[<slugs>]". Under 50 words.`,
    { phase: "Guides & Notes", label: "pro-notes" },
  ),
);

// --- Meta (for the skill): guidelines + component-directory ---
thunks.push(() =>
  agent(
    `You are a shaders.com docs harvest worker. Do NOT return file contents — only a short count.

1. Load the MCP tool: call ToolSearch with query exactly \`select:ReadMcpResourceTool\`.
2. Run \`mkdir -p ${META_DIR}\` once.
3. Read MCP resource server "shaders" uri "shaders://guidelines" -> write contents[0].text to ${META_DIR}/guidelines.md
4. Read MCP resource server "shaders" uri "shaders://component-directory" -> write contents[0].text to ${META_DIR}/component-directory.md
5. Return ONLY: "meta: written=<n> failed=[...]". Under 40 words.`,
    { phase: "Guides & Notes", label: "meta" },
  ),
);

log(`Harvesting: ${presetBatches.length} preset batches, ${Math.ceil(COMPONENTS.length / COMP_BATCH)} component batches, guides+pro-notes+meta`);

const results = await parallel(thunks);

return {
  agents: thunks.length,
  results: results.map((r) => (typeof r === "string" ? r : r === null ? "NULL" : r)),
};
