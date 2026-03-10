import { createHighlighter } from "shiki";

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

  if (!highlighterPromise) {
    highlighterPromise = createHighlighter({
      themes: ["github-light", "github-dark"],
      langs: LANGUAGES,
    });
  }

  highlighterPromise.then((h) => {
    highlighter.value = h;
  });

  return { highlighter, LANGUAGES };
}
