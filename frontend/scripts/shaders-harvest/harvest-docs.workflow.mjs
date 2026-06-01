export const meta = {
  name: "shaders-harvest-docs",
  description: "Harvest shaders.com docs (component docs + guides + pro-notes) — separate from presets so they aren't starved behind preset batches",
  phases: [{ title: "Component docs" }, { title: "Guides & Notes" }],
};

const ROOT = "/Users/nextifier/Herd/pmone/frontend";
const META_DIR = `${ROOT}/scripts/shaders-harvest/tmp/meta`;
const GUIDES_DIR = `${ROOT}/app/components/shaders-docs/guides`;
const PRONOTES_DIR = `${ROOT}/app/components/shaders-docs/pro-notes`;
const COMPONENTS_DIR = `${ROOT}/app/components/shaders-docs/components`;

const COMPONENTS = ["AngularBlur","Ascii","Aurora","BarShift","Beam","Blob","Blur","BrickPattern","BrightnessContrast","Bulge","ChannelBlur","Checkerboard","Chevron","ChromaFlow","ChromaticAberration","Circle","ColorWheel","ConcentricSpin","ConicGradient","ContourLines","Crescent","Cross","CRTScreen","Crystal","CursorRipples","CursorTrail","DiamondGradient","DiffuseBlur","Dither","DOMTexture","DotGrid","DropShadow","Duotone","Ellipse","Emboss","FallingLines","FilmGrain","FloatingParticles","Flower","FlowField","FlowingGradient","FlutedGlass","Fog","Form3D","FractalNoise","Glass","GlassTiles","Glitch","Glow","Godrays","Grayscale","Grid","GridDistortion","Group","Halftone","HexGrid","HueShift","ImageTexture","Invert","Kaleidoscope","LensFlare","LinearBlur","LinearGradient","Liquify","Marble","Mirror","MultiPointGradient","Neon","Paper","Perspective","Pixelate","Plasma","PolarCoordinates","Polygon","Posterize","ProgressiveBlur","RadialGradient","RectangularCoordinates","ReflectivePlane","Ring","Ripples","RoundedRect","Saturation","Sharpness","Shatter","SimplexNoise","SineWave","Smoke","SmokeFill","Solarize","SolidColor","Spherize","Spiral","Star","Strands","Stretch","Stripes","StudioBackground","SunBurst","Swirl","TiltShift","Tint","Trapezoid","Tritone","Truchet","Twirl","Vesica","VHS","Vibrance","VideoTexture","Vignette","Voronoi","WaveDistortion","Weave","WebcamTexture","WorleyNoise","ZoomBlur"];
const GUIDE_SLUGS = ["composing-effects","blending-masking","color-space","dynamic-props","hooks-events","layout-positioning","performance","props-reactivity","shape-effects","telemetry","transforms","quickstart","vue/quickstart","vue/ssr","index","mcp"];
const PRONOTE_SLUGS = ["color-spaces","composition","dynamic-prop-mapping","finishing-touches","hero-section-masking","interactions","media-effects","shape-effects-placement"];

const COMP_BATCH = 12;
function chunk(arr, size) {
  const out = [];
  for (let i = 0; i < arr.length; i += size) out.push(arr.slice(i, i + size));
  return out;
}

const thunks = [];

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
   - On a transient error, retry once, then skip and note it.
4. Return ONLY: "components: written=<n> skipped=<n> failed=[<names>]". Under 50 words.`,
      { phase: "Component docs", label: `components ${names[0]}…` },
    ),
  );
}

thunks.push(() =>
  agent(
    `You are a shaders.com docs harvest worker. Do NOT return file contents — only a short count.

1. Load the MCP tool: call ToolSearch with query exactly \`select:ReadMcpResourceTool\`.
2. Run \`mkdir -p ${GUIDES_DIR}/vue\` once.
3. For each guide slug in this list: ${JSON.stringify(GUIDE_SLUGS)}
   - Target file: ${GUIDES_DIR}/<slug>.md (note "vue/quickstart" -> ${GUIDES_DIR}/vue/quickstart.md). If it exists, SKIP.
   - Else read MCP resource server "shaders" uri "shaders://docs/guide/<slug>" and write contents[0].text verbatim.
4. Return ONLY: "guides: written=<n> skipped=<n> failed=[<slugs>]". Under 50 words.`,
    { phase: "Guides & Notes", label: "guides" },
  ),
);

thunks.push(() =>
  agent(
    `You are a shaders.com docs harvest worker. Do NOT return file contents — only a short count.

1. Load the MCP tool: call ToolSearch with query exactly \`select:ReadMcpResourceTool\`.
2. Run \`mkdir -p ${PRONOTES_DIR}\` once.
3. For each pro-note slug in this list: ${JSON.stringify(PRONOTE_SLUGS)}
   - Target file: ${PRONOTES_DIR}/<slug>.md . If it exists, SKIP.
   - Else read MCP resource server "shaders" uri "shaders://pro-notes/<slug>" and write contents[0].text verbatim.
4. Return ONLY: "pro-notes: written=<n> skipped=<n> failed=[<slugs>]". Under 50 words.`,
    { phase: "Guides & Notes", label: "pro-notes" },
  ),
);

thunks.push(() =>
  agent(
    `You are a shaders.com docs harvest worker. Do NOT return file contents — only a short count.

1. Load the MCP tool: call ToolSearch with query exactly \`select:ReadMcpResourceTool\`.
2. Run \`mkdir -p ${META_DIR}\` once.
3. If ${META_DIR}/guidelines.md does not exist: read MCP resource server "shaders" uri "shaders://guidelines" -> write contents[0].text to ${META_DIR}/guidelines.md
4. If ${META_DIR}/component-directory.md does not exist: read MCP resource server "shaders" uri "shaders://component-directory" -> write contents[0].text to ${META_DIR}/component-directory.md
5. Return ONLY: "meta: written=<n> skipped=<n>". Under 40 words.`,
    { phase: "Guides & Notes", label: "meta" },
  ),
);

log(`Harvesting docs: ${Math.ceil(COMPONENTS.length / COMP_BATCH)} component batches + guides + pro-notes + meta`);
const results = await parallel(thunks);
return { agents: thunks.length, results };
