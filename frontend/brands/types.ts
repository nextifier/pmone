export interface BrandMeta {
  /** Lowercase alphanumeric brand id; matches brands/<id>/ and public/brands/<id>/. */
  id: string;
  name: string;
  shortName: string;
  /** Production admin frontend origin (dev always uses http://localhost:3000). */
  siteUrl: string;
  /** Production API origin (dev always uses http://localhost:8000). */
  apiUrl: string;
  company: {
    name: string;
    address: string;
  };
  contact: {
    email: string;
    whatsapp: string;
  };
  manifestDescription: string;
  /**
   * Whether public/brands/<id>/ holds the real icon set. While false,
   * nuxt.config omits every icon/screenshot reference and useDynamicFavicon()
   * registers nothing, so no build ever points at a missing file.
   *
   * The full set, all of which must exist before flipping this to true:
   *
   *   favicon.ico                      byte-copy of icons/favicon-dark.ico,
   *                                    for consumers that hit /favicon.ico
   *   icons/favicon-light.svg          mark for a LIGHT browser theme
   *   icons/favicon-light.ico          same, 16/32/48 in one file
   *   icons/favicon-dark.svg           mark for a DARK browser theme
   *   icons/favicon-dark.ico           same, 16/32/48 in one file
   *   icons/apple-touch-icon.png       180x180, opaque
   *   icons/icon-192x192.png           192x192, opaque
   *   icons/icon-512x512.png           512x512, opaque
   *   icons/icon-512x512-maskable.png  512x512, opaque, inked pixels inside
   *                                    r = 0.40 of the width
   *   screenshots/desktop-1.png        1280x833
   *   screenshots/mobile-1.png         400x842
   *
   * Note the light/dark naming: -light is what a light-themed tab strip needs,
   * so it carries the DARK mark, and -dark carries the light one. The pair is
   * driven by the browser's prefers-color-scheme, not by the app's color mode.
   */
  assetsReady: boolean;
  /** Suggested values for the project "Organization" field (FormProject). */
  organizationOptions: string[];
}
