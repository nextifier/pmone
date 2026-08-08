import type { VariantProps } from "class-variance-authority";
import { cva } from "class-variance-authority";

export { default as Button } from "./Button.vue";

// shadcn-vue v4 cn-* button. Shape/colour come from the active `.style-X .cn-button*`
// rules (app/assets/css/styles). pmone keeps its cva KEYS (so call sites are
// untouched) and remaps the VALUES to cn-* classes: the extra `outline-destructive`
// stacks `cn-button-variant-outline-destructive` on top of outline, so a style that
// does not define it still renders a plain outline button; `iconSm`/`iconXs` map to
// the registry's `icon-sm`/`icon-xs`.
//
// No `disabled:pointer-events-none` on purpose. It and `disabled:cursor-not-allowed`
// cancel each other: an element with `pointer-events: none` is never the hit-test
// target, so its `cursor` is never applied and the pointer stays a plain arrow.
// `<button disabled>` already blocks click, focus and submit natively, so dropping it
// costs nothing there — and it lets a Tooltip wrapped around a disabled button work,
// which is usually the only way to explain WHY it is disabled. Trade-off: hover rules
// from `.cn-button-variant-*` now fire while disabled (most visible on `ghost`).
// `relative` anchors two absolutely positioned pseudo-elements: the inner hairline
// `.cn-button::before` a style may paint, and the coarse-pointer hit area below.
// `disabled:opacity-*` lives in the style sheets, not here — a utilities-layer value
// would win over `.cn-button` and lock every style to the same number.
//
// The hit area reads `--cn-touch-target` so a caller can switch it off with
// `[--cn-touch-target:0px]`. Two reasons to: the button owns `::after` for its own
// decoration (a `min-height` from here composes with the caller's `height` instead of
// losing to it on specificity, which blew the calendar's 3px today-dot up to 44px), or
// it sits in a dense grid where a 44px pad would steal its neighbours' taps.
export const buttonVariants = cva(
  "cn-button group/button relative inline-flex shrink-0 cursor-pointer items-center justify-center whitespace-nowrap tracking-tight transition-[color,box-shadow,transform] outline-none select-none disabled:cursor-not-allowed pointer-coarse:after:absolute pointer-coarse:after:size-full pointer-coarse:after:min-h-[var(--cn-touch-target,2.75rem)] pointer-coarse:after:min-w-[var(--cn-touch-target,2.75rem)] [&_svg]:pointer-events-none [&_svg]:shrink-0",
  {
    variants: {
      variant: {
        default: "cn-button-variant-default",
        destructive: "cn-button-variant-destructive",
        outline: "cn-button-variant-outline",
        "outline-destructive":
          "cn-button-variant-outline cn-button-variant-outline-destructive text-destructive-foreground",
        secondary: "cn-button-variant-secondary",
        ghost: "cn-button-variant-ghost",
        link: "cn-button-variant-link",
      },
      size: {
        default: "cn-button-size-default",
        xs: "cn-button-size-xs",
        sm: "cn-button-size-sm",
        lg: "cn-button-size-lg",
        // Not in the shadcn registry: one step above `lg` (+8px) in every style,
        // for hero / landing CTAs that would otherwise be hand-rolled.
        xl: "cn-button-size-xl",
        icon: "cn-button-size-icon",
        iconSm: "cn-button-size-icon-sm",
        iconXs: "cn-button-size-icon-xs",
        iconLg: "cn-button-size-icon-lg",
        // Kebab aliases so the ported shadcn showcase (which uses the registry's
        // `icon-sm`/`icon-xs`/`icon-lg` names) resolves to the same CSS as pmone's
        // camelCase `iconSm`/`iconXs`/`iconLg` call sites. Both are kept — additive,
        // no breakage.
        "icon-sm": "cn-button-size-icon-sm",
        "icon-xs": "cn-button-size-icon-xs",
        "icon-lg": "cn-button-size-icon-lg",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
);

export type ButtonVariants = VariantProps<typeof buttonVariants>;
