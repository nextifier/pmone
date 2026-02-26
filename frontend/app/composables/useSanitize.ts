import DOMPurify from "isomorphic-dompurify";

/**
 * Sanitizes HTML content to prevent XSS attacks
 * Uses DOMPurify for safe HTML rendering
 */
export function useSanitize() {
  /**
   * Sanitize HTML content
   * @param html - The HTML string to sanitize
   * @param options - DOMPurify configuration options
   */
  function sanitizeHtml(
    html: string | null | undefined,
    options: DOMPurify.Config = {}
  ): string {
    if (!html) return "";

    // Default configuration: allow common HTML elements and attributes
    const defaultOptions: DOMPurify.Config = {
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
