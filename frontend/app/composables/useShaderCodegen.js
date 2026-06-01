/**
 * Multi-framework code export for the shader editor.
 *
 * Uses the `shaders` package's own per-framework generators (Vue / React /
 * Svelte / Solid / JS) so the output matches shaders.com exactly: default-prop
 * omission, transform/mask handling, dynamic prop maps, and id-referencing are
 * all built in. The generators are pure string functions (no WebGPU, no
 * network), so they run anywhere and keep working with no subscription.
 *
 * Each generator is a small, self-contained module loaded on demand and cached.
 * The package omits the telemetry flag, so we always inject it afterwards to
 * match the project rule (every exported `<Shader>` disables telemetry).
 */
const FRAMEWORKS = [
  { value: "vue", label: "Vue", language: "vue" },
  { value: "react", label: "React", language: "jsx" },
  { value: "svelte", label: "Svelte", language: "html" },
  { value: "solid", label: "Solid", language: "jsx" },
  { value: "js", label: "JavaScript", language: "javascript" },
];

const LOADERS = {
  vue: () => import("shaders/vue/codegen"),
  react: () => import("shaders/react/codegen"),
  svelte: () => import("shaders/svelte/codegen"),
  solid: () => import("shaders/solid/codegen"),
  js: () => import("shaders/js/codegen"),
};

const moduleCache = {};

function injectTelemetry(code, framework) {
  switch (framework) {
    case "vue":
      return code.replace("<Shader", '<Shader :disable-telemetry="true"');
    case "react":
    case "solid":
    case "svelte":
      return code.replace("<Shader", "<Shader disableTelemetry");
    case "js":
      if (code.includes("\n}, {\n")) {
        return code.replace("\n}, {\n", "\n}, {\n  disableTelemetry: true,\n");
      }
      return code.replace(/\n\}\)\s*$/, "\n}, {\n  disableTelemetry: true,\n})");
    default:
      return code;
  }
}

export function useShaderCodegen() {
  /**
   * @param {{ components: any[] }} config
   * @param {{ framework?: string, colorSpace?: string, toneMapping?: string }} [opts]
   * @returns {Promise<string>} copy-pasteable source for the chosen framework
   */
  async function generate(config, opts = {}) {
    const { framework = "vue", colorSpace, toneMapping } = opts;
    const loader = LOADERS[framework] ?? LOADERS.vue;
    moduleCache[framework] ??= loader();
    const mod = await moduleCache[framework];
    const raw = mod.generatePresetCode(config ?? { components: [] }, colorSpace, toneMapping);
    return injectTelemetry(raw, framework);
  }

  return { frameworks: FRAMEWORKS, generate };
}
