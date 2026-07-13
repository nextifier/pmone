/**
 * Brand registry - the single place where deployments differ.
 *
 * THE BRAND-LAYER RULE: shared code never mentions a brand. Brand-owned
 * files under brands/<id>/ and public/brands/<id>/ may hardcode their own
 * brand freely. Shared code reads useAppConfig() (app.name, contact, ...)
 * or useRuntimeConfig().public (siteUrl, apiUrl) instead of literals.
 *
 * The active brand is selected at BUILD time via the BRAND env var
 * (defaults to "pmone"); nuxt.config.ts aliases #brand to brands/<id>.
 * Each brand's admin is its own deployment (Cloudflare Pages project)
 * building this same repo with a different BRAND value.
 *
 * Adding a brand: create brands/<id>/{meta.ts,Logo.vue,LogoMark.vue,Home.vue},
 * register it below, drop assets in public/brands/<id>/, set assetsReady.
 *
 * NO .vue imports in this file or in meta.ts files - nuxt.config.ts loads
 * them in a Node context at build time.
 */
import monara from "./monara/meta";
import pmone from "./pmone/meta";

export type { BrandMeta } from "./types";

export const brands = { pmone, monara } as const;

export type BrandId = keyof typeof brands;
