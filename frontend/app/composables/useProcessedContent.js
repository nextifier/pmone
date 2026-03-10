/**
 * Composable untuk memproses string HTML dari CMS.
 * Ia akan menemukan semua tag heading (h2-h6), memastikan mereka memiliki
 * ID yang unik, dan mengembalikannya sebagai computed property.
 * Juga menerapkan syntax highlighting menggunakan Shiki jika tersedia.
 * @param {import('vue').Ref<string>} htmlContent - Ref yang berisi string HTML mentah.
 */
export const useProcessedContent = (htmlContent) => {
  const { highlighter } = useShiki();

  const createUniqueId = (text, index) => {
    const slug = text
      .toLowerCase()
      .replace(/\s+/g, "-")
      .replace(/[^\w-]+/g, "");
    return `${slug}-${index}`;
  };

  const processedHtml = computed(() => {
    const rawHtml = htmlContent.value;
    if (!rawHtml || typeof document === "undefined") {
      let index = 0;
      return rawHtml.replace(
        /<h([2-6])(.*?)>(.*?)<\/h\1>/gi,
        (match, level, attrs, innerText) => {
          const text = innerText.replace(/<[^>]+>/g, "").trim();
          const newId = createUniqueId(text, index++);
          const cleanAttrs = attrs.replace(/\s*id="[^"]*"/i, "");
          return `<h${level} id="${newId}"${cleanAttrs}>${innerText}</h${level}>`;
        },
      );
    }

    const parser = new DOMParser();
    const doc = parser.parseFromString(rawHtml, "text/html");
    const headingNodes = doc.querySelectorAll("h2, h3, h4, h5, h6");

    headingNodes.forEach((node, index) => {
      const id = createUniqueId(node.innerText, index);
      node.id = id;
    });

    let html = doc.body.innerHTML;

    if (highlighter.value) {
      html = highlightCodeBlocks(html, highlighter.value);
    }

    return html;
  });

  return { processedHtml };
};

function highlightCodeBlocks(html, shiki) {
  return html.replace(
    /<pre><code(?:\s+class="language-(\w+)")?>([\s\S]*?)<\/code><\/pre>/g,
    (_, lang, code) => {
      if (!lang) return _;
      try {
        const decoded = code
          .replace(/&lt;/g, "<")
          .replace(/&gt;/g, ">")
          .replace(/&amp;/g, "&")
          .replace(/&quot;/g, '"')
          .replace(/&#39;/g, "'");
        return shiki.codeToHtml(decoded, {
          lang,
          themes: { light: "github-light", dark: "github-dark" },
          defaultColor: false,
        });
      } catch {
        return _;
      }
    },
  );
}
