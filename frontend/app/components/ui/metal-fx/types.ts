// Ported from metal-fx by Jakub Antalik (MIT License).
// https://github.com/Jakubantalik/metal-fx
// Vue port of src/types.ts — same public unions, with the React-only prop
// types (CSSProperties / ReactNode / HTMLAttributes / RefObject) dropped.
// The component props live in MetalFx.vue.

/**
 * Variant for the metal effect.
 * - 'button' (default): pill-shaped 134×40 baseline with shaderScale 1.6
 * - 'circle': compact 32×32 circle baseline with shaderScale 1.3
 *
 * In practice the wrapped child's measured dimensions drive the visible size —
 * the variant only controls the shader sampling scale and ring thickness.
 */
export type MetalFxVariant = "button" | "circle";

/**
 * Theme mode for the metal effect.
 *
 * - `auto` (default) resolves in two layers, live-updating on change:
 *   1. a `data-theme="dark|light"` attribute or `dark`/`light` class on any
 *      ancestor (the Tailwind / shadcn convention, which is what
 *      @nuxtjs/color-mode writes onto <html>), watched via `MutationObserver`;
 *   2. otherwise `matchMedia('(prefers-color-scheme: dark)')`, subscribed for
 *      live OS/browser theme switches.
 * - `dark` / `light` pin the tunings regardless of context.
 */
export type MetalFxTheme = "dark" | "light" | "auto";

/**
 * Bundled preset names. Each preset ships both a dark and light mode block.
 */
export type MetalFxPreset = "chromatic" | "silver" | "gold";
