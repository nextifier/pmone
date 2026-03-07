import DOMPurify from "dompurify";

/**
 * Sanitizes HTML content to prevent XSS attacks
 * Uses dompurify (browser-only) instead of isomorphic-dompurify which crashes on Cloudflare Workers
 */
export function useSanitize() {
  function sanitizeHtml(
    html: string | null | undefined,
    options: Record<string, unknown> = {}
  ): string {
    if (!html) return "";

    // On server (Cloudflare Workers), return HTML as-is since content comes from our own API
    if (import.meta.server) return html;

    const defaultOptions = {
      ALLOWED_TAGS: [
        "p",
        "br",
        "strong",
        "b",
        "em",
        "i",
        "u",
        "s",
        "strike",
        "h1",
        "h2",
        "h3",
        "h4",
        "h5",
        "h6",
        "ul",
        "ol",
        "li",
        "blockquote",
        "pre",
        "code",
        "a",
        "img",
        "hr",
        "div",
        "span",
        "table",
        "thead",
        "tbody",
        "tr",
        "th",
        "td",
        "figure",
        "figcaption",
      ],
      ALLOWED_ATTR: [
        "href",
        "src",
        "alt",
        "title",
        "class",
        "id",
        "target",
        "rel",
        "data-caption",
        "width",
        "height",
        "style",
      ],
      ALLOW_DATA_ATTR: true,
      ...options,
    };

    return DOMPurify.sanitize(html, defaultOptions);
  }

  /**
   * Transform img tags with data-caption into figure/figcaption for display
   */
  function wrapCaptionedImages(html: string): string {
    if (!html) return "";
    return html.replace(
      /<img([^>]*?)data-caption="([^"]+)"([^>]*?)>/g,
      '<figure><img$1data-caption="$2"$3><figcaption>$2</figcaption></figure>'
    );
  }

  return {
    sanitizeHtml,
    wrapCaptionedImages,
  };
}
