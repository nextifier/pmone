import type { ComputedRef, InjectionKey, Ref } from "vue";

/**
 * PreviewPanel is a layout primitive: an edit surface beside a live preview,
 * each scrolling on its own, collapsing to a Tabs switch when the panel itself
 * is too narrow to hold both.
 *
 * It never references `--navbar-height-*`, `--tabnav-height`, or any app token:
 * the page passes its offsets in, so the same component works in three repos
 * with three different headers.
 *
 * One thing worth knowing before adding chrome to it: `container-type:
 * inline-size` does NOT make the panel a containing block for `position: fixed`
 * descendants. That was assumed here at first and is wrong — a probe anchored to
 * the viewport, and the computed `contain` reads `none`. Only an explicit
 * `contain: layout`/`paint` would capture them. So the floating switcher is
 * plain `fixed`, matching every other pane switcher in the app.
 *
 * HOST INVARIANT (`mode="sticky"`): whatever follows the panel in the document
 * must be no taller than `insetBottom`. A sticky element stops sticking once
 * its containing block runs out, so with `pb-16` on the page and a 1rem
 * `insetBottom`, scrolling to the very bottom shoves the pinned preview 3rem up
 * and its top disappears under the page header. Pad the *inside* of the edit
 * slot instead - that height belongs to the containing block and costs nothing.
 * There is no way to fix this from in here: the offending padding lives on an
 * ancestor the panel cannot see or cancel.
 */

export type PreviewPanelTab = "edit" | "preview";
export type PreviewPanelThreshold = "4xl" | "5xl" | "6xl";
export type PreviewPanelRatio = "1:1" | "3:2" | "2:1" | "2:3";
export type PreviewPanelSide = "left" | "right";
export type PreviewPanelMode = "sticky" | "fill";

export interface PreviewPanelSplitClasses {
  /**
   * Panel width in px at which the panes go side by side. The number and the
   * class strings below live in the same row so they cannot drift. Nothing in
   * the component reads `px` — the layout is pure CSS — but a consumer that
   * genuinely needs to branch in JS gets the same value the classes came from.
   */
  px: number;
  /** Side-by-side above the threshold. */
  row: string;
  /** Same, with the preview on the left. */
  rowReverse: string;
  /**
   * Panes size to their content while stacked and share the row by grow factor
   * once split. Basis stays `auto` below the threshold so an auto-height column
   * never has to resolve a zero basis.
   */
  paneBasis: string;
  /**
   * Hide the inactive pane BELOW the threshold only.
   *
   * The inverse spelling — `data-[state=inactive]:hidden` plus
   * `@5xl/preview:block` — silently breaks the split: the data attribute puts
   * the `hidden` rule at specificity (0,2,0) while a container query adds none,
   * so `display:none` wins at every width and the preview pane never appears.
   * `@max-` emits no `display:none` above the threshold at all, so there is no
   * conflict to lose.
   */
  stackHide: string;
  /** Clearance beneath the scrollers for the floating switcher. */
  stackClear: string;
  /** The switcher is only an affordance while the panes are stacked. */
  switcherOff: string;
  /**
   * Scroll chaining is only contained once a pane really scrolls. Unconditional
   * `overscroll-contain` kills pull-to-refresh: Chrome blocks chaining out of an
   * `overflow:auto` box even when its scroll range is zero.
   */
  overscroll: string;
  /**
   * Panes size to their own content instead of stretching to the row. Without
   * this the preview pane would be as tall as the edit pane and have nothing to
   * stick within.
   */
  alignStart: string;
  /**
   * Pins the preview pane once the panes are side by side. Height is derived
   * from the two insets rather than from `bottom`, because a sticky box honours
   * one edge at a time — `top` and `bottom` together would not give it a size.
   */
  previewStick: string;
}

/**
 * Container-query rows. Tailwind only ever sees literal class names here, which
 * is why every threshold spells its variants out in full instead of composing
 * them from the key.
 *
 * `5xl` (1024px) is the default. `4xl` would split at ~448px per pane with the
 * sidebar open, too narrow for a form beside a preview; `6xl` puts the boundary
 * at exactly 1440px with the sidebar expanded, the most common laptop width
 * here, so subpixel rounding and browser zoom would make it flap.
 */
export const PREVIEW_PANEL_SPLIT = {
  "4xl": {
    px: 896,
    row: "@4xl/preview:flex-row",
    rowReverse: "@4xl/preview:flex-row-reverse",
    paneBasis: "@4xl/preview:basis-0",
    stackHide: "@max-4xl/preview:data-[state=inactive]:hidden",
    stackClear: "@max-4xl/preview:pb-24",
    switcherOff: "@4xl/preview:invisible @4xl/preview:opacity-0",
    overscroll: "@4xl/preview:overscroll-contain",
    alignStart: "@4xl/preview:items-start",
    previewStick:
      "@4xl/preview:sticky @4xl/preview:top-(--pv-offset) @4xl/preview:h-[calc(100svh-var(--pv-offset)-var(--pv-inset-b))]",
  },
  "5xl": {
    px: 1024,
    row: "@5xl/preview:flex-row",
    rowReverse: "@5xl/preview:flex-row-reverse",
    paneBasis: "@5xl/preview:basis-0",
    stackHide: "@max-5xl/preview:data-[state=inactive]:hidden",
    stackClear: "@max-5xl/preview:pb-24",
    switcherOff: "@5xl/preview:invisible @5xl/preview:opacity-0",
    overscroll: "@5xl/preview:overscroll-contain",
    alignStart: "@5xl/preview:items-start",
    previewStick:
      "@5xl/preview:sticky @5xl/preview:top-(--pv-offset) @5xl/preview:h-[calc(100svh-var(--pv-offset)-var(--pv-inset-b))]",
  },
  "6xl": {
    px: 1152,
    row: "@6xl/preview:flex-row",
    rowReverse: "@6xl/preview:flex-row-reverse",
    paneBasis: "@6xl/preview:basis-0",
    stackHide: "@max-6xl/preview:data-[state=inactive]:hidden",
    stackClear: "@max-6xl/preview:pb-24",
    switcherOff: "@6xl/preview:invisible @6xl/preview:opacity-0",
    overscroll: "@6xl/preview:overscroll-contain",
    alignStart: "@6xl/preview:items-start",
    previewStick:
      "@6xl/preview:sticky @6xl/preview:top-(--pv-offset) @6xl/preview:h-[calc(100svh-var(--pv-offset)-var(--pv-inset-b))]",
  },
} as const satisfies Record<PreviewPanelThreshold, PreviewPanelSplitClasses>;

/**
 * Ratios are `flex-grow` factors applied inline, not classes, so they do not
 * multiply against the threshold map.
 */
export const PREVIEW_PANEL_GROW: Record<
  PreviewPanelRatio,
  { edit: number; preview: number }
> = {
  "1:1": { edit: 1, preview: 1 },
  "3:2": { edit: 3, preview: 2 },
  "2:1": { edit: 2, preview: 1 },
  "2:3": { edit: 2, preview: 3 },
};

export const PREVIEW_PANEL_DEFAULTS = {
  defaultTab: "edit" as const,
  threshold: "5xl" as const,
  ratio: "1:1" as const,
  side: "right" as const,
  mode: "sticky" as const,
  offset: "0px",
  insetBottom: "1rem",
  editLabel: "Edit",
  previewLabel: "Preview",
};

/**
 * How the panel is sized.
 *
 * `sticky` — the page scrolls the way it always does and the EDIT pane goes
 * with it, no inner scroller and no card of its own. Only the preview pins,
 * `--pv-offset` below the viewport top and `--pv-inset-b` clear of its bottom,
 * so it never butts against the header or the edge of the screen. Its travel
 * comes from the edit pane being taller than it — which is also why the panes
 * row aligns to the start rather than stretching.
 *
 * `fill` — the parent hands down a definite height and both panes scroll
 * inside it. For surfaces that own their viewport, like a dialog.
 *
 * `svh`, not `lvh` or `dvh`: `lvh` overshoots by the mobile toolbar height on
 * the tablets where the split is actually reachable, and `dvh` would relayout
 * the whole split on every browser-chrome animation.
 */
export const previewPanelModeClasses: Record<PreviewPanelMode, string> = {
  sticky: "",
  fill: "min-h-0 flex-1",
};

export interface PreviewPanelContext {
  split: ComputedRef<PreviewPanelSplitClasses>;
  /** Bumped by `reload()`; keys the preview body so it remounts. */
  reloadKey: Ref<number>;
  reload: () => void;
}

export const PREVIEW_PANEL_CONTEXT: InjectionKey<PreviewPanelContext> =
  Symbol("PreviewPanelContext");

/** Fallback so Pane and Surface can be composed outside a PreviewPanel root. */
export const PREVIEW_PANEL_FALLBACK_SPLIT =
  PREVIEW_PANEL_SPLIT[PREVIEW_PANEL_DEFAULTS.threshold];

export interface PreviewPanelSwitcherItem {
  value: string;
  label: string;
  icon?: string;
}

export const previewPanelSwitcherSizeClasses = {
  sm: "h-7 px-2.5 text-xs",
  md: "h-8 px-4 text-sm",
} as const;

export type PreviewPanelSwitcherSize =
  keyof typeof previewPanelSwitcherSizeClasses;

