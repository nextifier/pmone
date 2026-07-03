// Preset codes for the Appearance customizer (Copy Preset / Open Preset / Shuffle).
//
// Mirrors shadcn /create's `--preset xxx` UX with a pmone-native encoding: the
// selection is 7 enum fields (style, baseColor, theme, chartColor, radius, font,
// fontHeading), each stored as an INDEX into its known list. Indices pack into a
// tiny byte array (version + 7 indices) → base64url → a short, shareable code.
// Positional indices stay stable because the underlying lists are fixed enums.
// Client-only (btoa/atob) — only ever run from clipboard/paste handlers.

import type { AppearanceConfig } from "./index";
import {
  BASE_COLOR_NAMES,
  CHART_COLOR_NAMES,
  DEFAULT_APPEARANCE,
  RADII,
  STYLE_NAMES,
  THEME_NAMES,
} from "./index";
import { FONT_HEADING_OPTIONS, FONTS } from "@/lib/fonts";

/** Selectable body-font values ("default" = MinusOne). */
const FONT_VALUES = ["default", ...FONTS.map(f => f.value)];
/** Selectable heading-font values ("inherit" = follow body font). */
const HEADING_VALUES = FONT_HEADING_OPTIONS.map(f => f.value);
const RADIUS_NAMES = RADII.map(r => r.name);

type PresetConfig = Required<Pick<
  AppearanceConfig,
  "style" | "baseColor" | "theme" | "chartColor" | "radius" | "font" | "fontHeading"
>>;

interface FieldSpec {
  key: keyof PresetConfig;
  list: readonly string[];
  def: string;
}

/** Field order is FIXED — appending is safe, reordering breaks old codes. */
const FIELDS: FieldSpec[] = [
  { key: "style", list: STYLE_NAMES, def: DEFAULT_APPEARANCE.style! },
  { key: "baseColor", list: BASE_COLOR_NAMES, def: DEFAULT_APPEARANCE.baseColor },
  { key: "theme", list: THEME_NAMES, def: DEFAULT_APPEARANCE.theme },
  { key: "chartColor", list: CHART_COLOR_NAMES, def: DEFAULT_APPEARANCE.chartColor! },
  { key: "radius", list: RADIUS_NAMES, def: DEFAULT_APPEARANCE.radius! },
  { key: "font", list: FONT_VALUES, def: DEFAULT_APPEARANCE.font! },
  { key: "fontHeading", list: HEADING_VALUES, def: DEFAULT_APPEARANCE.fontHeading! },
];

const VERSION = 1;

function bytesToBase64Url(bytes: number[]): string {
  let binary = "";
  for (const byte of bytes) {
    binary += String.fromCharCode(byte);
  }
  return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
}

function base64UrlToBytes(value: string): number[] {
  const base64 = value.replace(/-/g, "+").replace(/_/g, "/");
  const binary = atob(base64);
  return Array.from(binary, char => char.charCodeAt(0));
}

/** Resolve a (possibly partial) config to the 7 preset fields, filling defaults. */
export function toPresetConfig(config: Partial<AppearanceConfig> | null): PresetConfig {
  return {
    style: config?.style ?? DEFAULT_APPEARANCE.style!,
    baseColor: config?.baseColor ?? DEFAULT_APPEARANCE.baseColor,
    theme: config?.theme ?? DEFAULT_APPEARANCE.theme,
    chartColor: config?.chartColor ?? DEFAULT_APPEARANCE.chartColor!,
    radius: config?.radius ?? DEFAULT_APPEARANCE.radius!,
    font: config?.font ?? DEFAULT_APPEARANCE.font!,
    fontHeading: config?.fontHeading ?? DEFAULT_APPEARANCE.fontHeading!,
  };
}

/** Encode a config into a short base64url preset code. */
export function encodePreset(config: Partial<AppearanceConfig> | null): string {
  const resolved = toPresetConfig(config);
  const bytes = [VERSION];
  for (const field of FIELDS) {
    const value = resolved[field.key];
    let index = field.list.indexOf(value);
    if (index < 0) {
      index = Math.max(0, field.list.indexOf(field.def));
    }
    bytes.push(index);
  }
  return bytesToBase64Url(bytes);
}

/** Decode a preset code back to a full config, or null when malformed. */
export function decodePreset(code: string): PresetConfig | null {
  try {
    const bytes = base64UrlToBytes(code.trim());
    if (bytes[0] !== VERSION || bytes.length !== FIELDS.length + 1) {
      return null;
    }
    const config = {} as PresetConfig;
    for (let i = 0; i < FIELDS.length; i++) {
      const field = FIELDS[i]!;
      const value = field.list[bytes[i + 1]!];
      if (value == null) {
        return null;
      }
      config[field.key] = value;
    }
    return config;
  } catch {
    return null;
  }
}

/** Strip a leading `--preset` / whitespace, then decode. Returns null when invalid. */
export function parsePresetInput(input: string): PresetConfig | null {
  const cleaned = input.trim().replace(/^--preset\s+/i, "").trim();
  if (!cleaned) {
    return null;
  }
  return decodePreset(cleaned);
}

/** Pick a random element of a list. `index` varies the label for reproducibility elsewhere. */
function pick<T>(list: readonly T[]): T {
  return list[Math.floor(Math.random() * list.length)]!;
}

/**
 * A random-but-tasteful selection for the Shuffle button. Colors/radius/style
 * shuffle freely; fonts bias toward the app defaults so results stay legible.
 */
export function randomPreset(): PresetConfig {
  return {
    style: pick(STYLE_NAMES),
    baseColor: pick(BASE_COLOR_NAMES),
    theme: pick(THEME_NAMES),
    chartColor: pick(THEME_NAMES),
    radius: pick(RADIUS_NAMES),
    font: Math.random() < 0.6 ? "default" : pick(FONT_VALUES),
    fontHeading: Math.random() < 0.6 ? "inherit" : pick(HEADING_VALUES),
  };
}
