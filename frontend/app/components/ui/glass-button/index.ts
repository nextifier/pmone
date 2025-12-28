import type { VariantProps } from "class-variance-authority";
import { cva } from "class-variance-authority";

export { default as GlassButton } from "./GlassButton.vue";

export const glassButtonVariants = cva("", {
  variants: {
    variant: {
      default: "",
    },
    size: {
      default: "",
    },
  },
  defaultVariants: {
    variant: "default",
    size: "default",
  },
});

export type GlassButtonVariants = VariantProps<typeof glassButtonVariants>;
