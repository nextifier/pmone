import type { VariantProps } from "class-variance-authority";
import { cva } from "class-variance-authority";

export { default as Button } from "./Button.vue";

// shadcn-vue v4 cn-* button. Shape/colour come from the active `.style-X .cn-button*`
// rules (app/assets/css/styles). pmone keeps its cva KEYS (so call sites are
// untouched) and remaps the VALUES to cn-* classes: the extra `outline-destructive`
// composes outline + `text-destructive`; `iconSm`/`iconXs` map to the registry's
// `icon-sm`/`icon-xs`.
export const buttonVariants = cva(
  "cn-button group/button inline-flex shrink-0 cursor-pointer items-center justify-center whitespace-nowrap tracking-tight transition-[color,box-shadow,transform] outline-none select-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0",
  {
    variants: {
      variant: {
        default: "cn-button-variant-default",
        destructive: "cn-button-variant-destructive",
        outline: "cn-button-variant-outline",
        "outline-destructive": "cn-button-variant-outline text-destructive-foreground",
        secondary: "cn-button-variant-secondary",
        ghost: "cn-button-variant-ghost",
        link: "cn-button-variant-link",
      },
      size: {
        default: "cn-button-size-default",
        xs: "cn-button-size-xs",
        sm: "cn-button-size-sm",
        lg: "cn-button-size-lg",
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
