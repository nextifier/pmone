import { createHighlighterCore } from "shiki/core";
import { createOnigurumaEngine } from "shiki/engine/oniguruma";

const LANGUAGES = [
  "javascript",
  "typescript",
  "html",
  "css",
  "php",
  "json",
  "bash",
  "sql",
  "python",
  "vue",
  "jsx",
  "tsx",
  "markdown",
  "yaml",
  "xml",
];

let highlighterPromise = null;

export function useShiki() {
  const highlighter = shallowRef(null);

  // Use shiki/core with explicit dynamic grammar imports instead of the full
  // `shiki` bundle. The full bundle statically pulls all ~357 grammars (~10 MB)
  // regardless of `langs`, which inflated the Cloudflare worker. This ships
  // only the 15 languages we actually highlight.
  if (!highlighterPromise) {
    highlighterPromise = createHighlighterCore({
      themes: [
        import("shiki/themes/github-light.mjs"),
        import("shiki/themes/github-dark.mjs"),
      ],
      langs: [
        import("shiki/langs/javascript.mjs"),
        import("shiki/langs/typescript.mjs"),
        import("shiki/langs/html.mjs"),
        import("shiki/langs/css.mjs"),
        import("shiki/langs/php.mjs"),
        import("shiki/langs/json.mjs"),
        import("shiki/langs/bash.mjs"),
        import("shiki/langs/sql.mjs"),
        import("shiki/langs/python.mjs"),
        import("shiki/langs/vue.mjs"),
        import("shiki/langs/jsx.mjs"),
        import("shiki/langs/tsx.mjs"),
        import("shiki/langs/markdown.mjs"),
        import("shiki/langs/yaml.mjs"),
        import("shiki/langs/xml.mjs"),
      ],
      engine: createOnigurumaEngine(import("shiki/wasm")),
    });
  }

  highlighterPromise.then((h) => {
    highlighter.value = h;
  });

  return { highlighter, LANGUAGES };
}
