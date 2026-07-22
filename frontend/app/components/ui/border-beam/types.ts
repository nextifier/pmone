// Ported from border-beam by Jakub Antalik (MIT License).
// https://github.com/Jakubantalik/border-beam
// Vue port of src/types.ts — same public unions and config interfaces, with
// the React-only prop types (CSSProperties / ReactNode / HTMLAttributes)
// dropped. The component props live in BorderBeam.vue.

/**
 * Size/type preset for the border beam effect
 *
 * Rotate family (traveling/spinning beam):
 * - 'sm': Small button-sized with compact glow
 * - 'md': Medium card-sized with full border glow
 * - 'line': Bottom-only traveling glow with breathe and spike animations
 *
 * Pulse family (breathing glow, no rotation):
 * - 'pulse-outside': Glow blooms OUTWARD beyond the element (uncropped halo)
 * - 'pulse-inner': Glow breathes contained within the element's border
 */
export type BorderBeamSize =
  | "sm"
  | "md"
  | "line"
  | "pulse-outside"
  | "pulse-inner";

/**
 * Theme mode for adapting beam colors to background.
 *
 * `auto` resolves in two layers, live-updating on change:
 *   1. a `data-theme="dark|light"` attribute or `dark`/`light` class on any
 *      ancestor (the Tailwind / shadcn convention, which is what
 *      @nuxtjs/color-mode writes onto <html>), watched via `MutationObserver`;
 *   2. otherwise `matchMedia('(prefers-color-scheme: dark)')`, subscribed for
 *      live OS/browser theme switches.
 *
 * `dark` / `light` pin the palette regardless of context.
 */
export type BorderBeamTheme = "dark" | "light" | "auto";

/**
 * Color variant for the beam effect
 * - 'colorful': Full rainbow spectrum (default)
 * - 'mono': Monochromatic grayscale
 * - 'ocean': Blue and purple tones
 * - 'sunset': Warm orange, yellow, and red tones
 */
export type BorderBeamColorVariant = "colorful" | "mono" | "ocean" | "sunset";

/**
 * Configuration for a size preset
 */
export interface SizeConfig {
  borderRadius: number;
  borderWidth: number;
  width?: number;
  height?: number;
}

/**
 * Theme color configuration
 */
export interface ThemeColors {
  strokeOpacity: number;
  innerOpacity: number;
  bloomOpacity: number;
  innerShadow: string;
  saturation: number;
  /** Optional per-type default brightness (used by pulse types). Falls back to 1.3. */
  brightness?: number;
  /**
   * Optional opacity of the 1px hairline border that frames the element.
   * Used by 'pulse-outside' so the colored stroke rides a subtle outline,
   * matching the v5 prototype. Falls back to 0 (no hairline).
   */
  hairlineOpacity?: number;
}
