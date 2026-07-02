// Curated font subset for the Appearance system (Heading + Font pickers).
//
// Each family MUST also be declared in `nuxt.config.ts` `fonts.families` so
// @nuxt/fonts self-hosts it — the families are applied at RUNTIME via injected
// CSS vars (see lib/appearance/index.ts), which @nuxt/fonts' static scanner
// cannot see. Body default stays MinusOne (declared in main.css `@theme`).
//
// Shape mirrors levenium apps/ui/app/lib/fonts.ts so the ported showcase
// (StyleOverview.vue) consumes `FONTS` unchanged.

export interface FontOption {
  name: string;
  value: string;
  cssVar: string;
  fontFamily: string;
  type: "sans" | "mono" | "serif" | "default";
}

export const FONTS: FontOption[] = [
  { name: "Geist", value: "geist", cssVar: "--font-geist", fontFamily: "'Geist', sans-serif", type: "sans" },
  { name: "Inter", value: "inter", cssVar: "--font-inter", fontFamily: "'Inter', sans-serif", type: "sans" },
  { name: "DM Sans", value: "dm-sans", cssVar: "--font-dm-sans", fontFamily: "'DM Sans', sans-serif", type: "sans" },
  { name: "Manrope", value: "manrope", cssVar: "--font-manrope", fontFamily: "'Manrope', sans-serif", type: "sans" },
  { name: "Space Grotesk", value: "space-grotesk", cssVar: "--font-space-grotesk", fontFamily: "'Space Grotesk', sans-serif", type: "sans" },
  { name: "Outfit", value: "outfit", cssVar: "--font-outfit", fontFamily: "'Outfit', sans-serif", type: "sans" },
  { name: "Geist Mono", value: "geist-mono", cssVar: "--font-geist-mono", fontFamily: "'Geist Mono', monospace", type: "mono" },
  { name: "JetBrains Mono", value: "jetbrains-mono", cssVar: "--font-jetbrains-mono", fontFamily: "'JetBrains Mono', monospace", type: "mono" },
  { name: "Playfair Display", value: "playfair-display", cssVar: "--font-playfair-display", fontFamily: "'Playfair Display', serif", type: "serif" },
  { name: "Lora", value: "lora", cssVar: "--font-lora", fontFamily: "'Lora', serif", type: "serif" },
];

export const FONT_HEADING_OPTIONS: FontOption[] = [
  { name: "Inherit", value: "inherit", cssVar: "", fontFamily: "inherit", type: "default" },
  ...FONTS,
];

/** Values that mean "use the app default (MinusOne)" — no override emitted. */
const FONT_UNSET = new Set(["", "default"]);

/** Resolve a body-font value to its CSS `font-family`, or undefined when unset. */
export function getFontFamily(value?: string | null): string | undefined {
  if (!value || FONT_UNSET.has(value)) {
    return undefined;
  }
  return FONTS.find(f => f.value === value)?.fontFamily;
}

/**
 * Resolve a heading-font value to its CSS `font-family`, or undefined when it
 * should inherit the body font (`inherit`/unset).
 */
export function getHeadingFontFamily(value?: string | null): string | undefined {
  if (!value || FONT_UNSET.has(value) || value === "inherit") {
    return undefined;
  }
  return FONTS.find(f => f.value === value)?.fontFamily;
}
