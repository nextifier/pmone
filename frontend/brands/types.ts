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
   * Whether public/brands/<id>/ holds real favicon/PWA icons/screenshots.
   * While false, nuxt.config omits every icon/screenshot reference so the
   * build never points at missing files.
   */
  assetsReady: boolean;
  /** Suggested values for the project "Organization" field (FormProject). */
  organizationOptions: string[];
}
