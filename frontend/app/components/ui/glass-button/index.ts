import type { VariantProps } from "class-variance-authority";
import { cva } from "class-variance-authority";

export { default as GlassButton } from "./GlassButton.vue";

export const glassButtonVariants = cva("", {
  variants: {
    variant: {
      default: "glass-button-variant-default",
      outline: "glass-button-variant-outline",
      destructive: "glass-button-variant-destructive",
      "destructive-outline": "glass-button-variant-destructive-outline",
      secondary: "glass-button-variant-secondary",
      ghost: "glass-button-variant-ghost",
      link: "glass-button-variant-link",
      invert: "glass-button-variant-invert",
      "outline-invert": "glass-button-variant-outline-invert",
    },
    size: {
      xs: "h-7 gap-1 px-2.5 text-xs sm:h-6",
      sm: "h-8 gap-1.5 px-3 text-sm sm:h-7",
      default: "h-9 gap-2 px-4 text-sm sm:h-8",
      lg: "h-10 gap-2 px-5 text-base sm:h-9",
      xl: "h-11 gap-2.5 px-6 text-lg sm:h-10 sm:text-base",
      icon: "size-9 sm:size-8",
      "icon-xs": "size-7 sm:size-6",
      "icon-sm": "size-8 sm:size-7",
      "icon-lg": "size-10 sm:size-9",
      "icon-xl": "size-11 sm:size-10",
      inherit: "text-[1em] px-[1em] py-[0.5em]",
    },
  },
  defaultVariants: {
    variant: "default",
    size: "default",
  },
});

export type GlassButtonVariants = VariantProps<typeof glassButtonVariants>;
