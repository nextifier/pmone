// shiki/core and the oniguruma engine are imported dynamically, inside the
// client-only branch below, so neither they nor the grammar chunks reach the
// server bundle.

// NOTE: the language list intentionally differs per repo — levenium ships only
// what its docs highlight, while pmone and pmone-events also need php, sql,
// python, tsx, markdown and xml. Everything else in this file is identical
// across the three.
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

  // Client only. The oniguruma engine compiles a WebAssembly module from bytes
  // at runtime, which Cloudflare Workers forbid — on the server this rejects
  // and, with no `.catch`, takes the isolate's unhandled-rejection path. The
  // server renders CodeBlock's plain <pre> fallback, which is byte-identical to
  // the client's first paint before the highlighter resolves, so there is no
  // hydration mismatch.
  //
  // Grammars are imported one by one rather than via the full `shiki` bundle:
  // that bundle statically pulls all ~357 grammars (~10 MB) regardless of
  // `langs`, which used to inflate the worker.
  if (import.meta.client) {
    if (!highlighterPromise) {
      highlighterPromise = (async () => {
        const [{ createHighlighterCore }, { createOnigurumaEngine }] =
          await Promise.all([
            import("shiki/core"),
            import("shiki/engine/oniguruma"),
          ]);

        return createHighlighterCore({
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
      })();
    }

    highlighterPromise
      .then((h) => {
        highlighter.value = h;
      })
      // Leave `highlighter` null so CodeBlock keeps its plain <pre> fallback.
      .catch(() => {});
  }

  return { highlighter, LANGUAGES };
}
