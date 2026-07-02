// Lightweight appearance (design-token) engine — token-only, ZERO runtime deps.
//
// Turns an { baseColor, theme, chartColor, radius? } selection into the CSS-var
// token sets (light + dark) that every token-based component reads. Does NOT use
// the shadcn cn-* "Style" system, fonts, icon libraries, zod, or shadcn-vue pkg,
// so this whole `app/lib/appearance/` folder is safe + cheap to copy verbatim
// into pmone (/frontend) and pmone-events (layers/base). Keep the folder in sync
// across repos. Never put theming logic in `components/ui` (must stay identical).
//
// Source palettes: shadcn token themes (MIT), via levenium registry/themes.ts.

import type { AppearanceTheme } from "./themes";
import { THEMES } from "./themes";
import { getFontFamily, getHeadingFontFamily } from "@/lib/fonts";

export type { AppearanceTheme };
export { THEMES };

// Base colors = the neutral palette subset (background / muted / border tones).
export const BASE_COLOR_NAMES = [
  "neutral",
  "stone",
  "zinc",
  "mauve",
  "olive",
  "mist",
  "taupe",
] as const;

// Theme + chart color = any of the 24 themes (accent / chart palettes).
export const THEME_NAMES = THEMES.map(t => t.name);
export const CHART_COLOR_NAMES = THEME_NAMES;

export const RADII = [
  { name: "default", title: "Default", value: "0.625rem" },
  { name: "none", title: "None", value: "0rem" },
  { name: "small", title: "Small", value: "0.375rem" },
  { name: "medium", title: "Medium", value: "0.5rem" },
  { name: "large", title: "Large", value: "0.875rem" },
] as const;

// shadcn-vue v4 "Style" names — each maps to a `.style-X .cn-*` CSS file in
// app/assets/css/styles. Drives component SHAPE (radius/padding/border/shadow per
// component); composes with the color tokens above. A style is always active.
export const STYLE_NAMES = [
  "mono",
  "vega",
  "nova",
  "maia",
  "lyra",
  "mira",
  "luma",
  "sera",
  "rhea",
] as const;

// "mono" reproduces pmone's original component look (pre-cn-* / production) and is
// the default, so the admin looks unchanged unless a user opts into a shadcn style.
export const DEFAULT_STYLE = "mono";

// Styles that are intentionally hard-edged — radius is forced to 0 (mirrors
// shadcn /create RADIUS_LOCKED_STYLES). Enforced via CSS in main.css.
export const RADIUS_LOCKED_STYLES = ["lyra", "sera"] as const;

export interface AppearanceConfig {
  baseColor: string;
  theme: string;
  chartColor?: string;
  radius?: string; // RADII name, e.g. "default" | "small" | ...
  style?: string; // STYLE_NAMES, e.g. "vega" | "nova" | ...
  font?: string; // FONTS value, e.g. "geist" | "inter" | ... ("default" = MinusOne)
  fontHeading?: string; // FONT_HEADING_OPTIONS value ("inherit" = follow body font)
}

export const DEFAULT_APPEARANCE: AppearanceConfig = {
  baseColor: "neutral",
  theme: "neutral",
  chartColor: "neutral",
  radius: "default",
  style: DEFAULT_STYLE,
  font: "default",
  fontHeading: "inherit",
};

const CHART_KEYS = ["chart-1", "chart-2", "chart-3", "chart-4", "chart-5"];

export function getAppearanceTheme(name: string): AppearanceTheme | undefined {
  return THEMES.find(t => t.name === name);
}

/** Small option lists for building pickers (value + label + a swatch color). */
export interface AppearanceOption {
  value: string;
  label: string;
  swatch: string;
}

function toOptions(names: readonly string[], swatchKey: string): AppearanceOption[] {
  return names
    .map((name) => {
      const t = getAppearanceTheme(name);
      if (!t) {
        return null;
      }
      return {
        value: name,
        label: t.title,
        swatch: t.cssVars.light[swatchKey] ?? t.cssVars.light.primary ?? "",
      };
    })
    .filter((o): o is AppearanceOption => o !== null);
}

export const BASE_COLOR_OPTIONS = toOptions(BASE_COLOR_NAMES, "muted-foreground");
export const THEME_OPTIONS = toOptions(THEME_NAMES, "primary");
export const CHART_COLOR_OPTIONS = toOptions(CHART_COLOR_NAMES, "chart-1");

/**
 * Merge baseColor + theme (+ optional chartColor + radius) into the final
 * light/dark CSS-var maps. Mirrors levenium's buildRegistryTheme: theme tokens
 * override base-color tokens; chartColor overrides only chart-1..5.
 */
export function buildAppearanceTheme(config: AppearanceConfig): {
  light: Record<string, string>;
  dark: Record<string, string>;
} {
  const base = getAppearanceTheme(config.baseColor) ?? getAppearanceTheme("neutral")!;
  const theme = getAppearanceTheme(config.theme) ?? getAppearanceTheme("neutral")!;

  const light: Record<string, string> = {
    ...base.cssVars.light,
    ...theme.cssVars.light,
  };
  const dark: Record<string, string> = {
    ...base.cssVars.dark,
    ...theme.cssVars.dark,
  };

  if (config.chartColor) {
    const chart = getAppearanceTheme(config.chartColor);
    if (chart) {
      for (const key of CHART_KEYS) {
        if (chart.cssVars.light[key]) {
          light[key] = chart.cssVars.light[key];
        }
        if (chart.cssVars.dark[key]) {
          dark[key] = chart.cssVars.dark[key];
        }
      }
    }
  }

  if (config.radius && config.radius !== "default") {
    const radius = RADII.find(r => r.name === config.radius);
    if (radius) {
      light.radius = radius.value;
    }
  }

  // Sidebar always tracks the page background (per product decision): force the
  // sidebar surface/foreground to the base background/foreground so no theme can
  // reintroduce a distinct sidebar tint. Pairs with `--sidebar: var(--background)`
  // in main.css for the non-customized (native palette) case.
  if (light.background) {
    light.sidebar = light.background;
  }
  if (light.foreground) {
    light["sidebar-foreground"] = light.foreground;
  }
  if (dark.background) {
    dark.sidebar = dark.background;
  }
  if (dark.foreground) {
    dark["sidebar-foreground"] = dark.foreground;
  }

  // Fonts are applied via CSS vars (SSR-safe, reactive) — only emit an override
  // when a non-default family is chosen so MinusOne stays the default. `--font-sans`
  // re-fonts the whole app (Tailwind `font-sans` reads it); `--font-heading` only
  // affects `.cn-font-heading` elements (falls back to `--font-sans`).
  const fontSans = getFontFamily(config.font);
  if (fontSans) {
    light["font-sans"] = fontSans;
  }
  const fontHeading = getHeadingFontFamily(config.fontHeading);
  if (fontHeading) {
    light["font-heading"] = fontHeading;
  }

  return { light, dark };
}

function declarations(vars: Record<string, string>): string {
  return Object.entries(vars)
    .filter(([, value]) => Boolean(value))
    .map(([key, value]) => `--${key}:${value};`)
    .join("");
}

/**
 * Produce a CSS string to inject (e.g. via useHead `<style>`): scoped tokens for
 * light (rootSelector) + dark (darkSelector). SSR-friendly — no FOUC.
 */
export function appearanceCss(
  config: AppearanceConfig,
  options: { rootSelector?: string; darkSelector?: string } = {},
): string {
  const { rootSelector = ":root", darkSelector = ".dark" } = options;
  const { light, dark } = buildAppearanceTheme(config);
  return `${rootSelector}{${declarations(light)}}${darkSelector}{${declarations(dark)}}`;
}
