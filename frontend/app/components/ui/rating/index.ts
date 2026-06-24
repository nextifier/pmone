import type { VariantProps } from "class-variance-authority";
import { cva } from "class-variance-authority";

export { default as Rating } from "./Rating.vue";

// Container: jarak antar bintang per ukuran.
export const ratingVariants = cva("inline-flex items-center outline-none", {
  variants: {
    size: {
      sm: "gap-2",
      default: "gap-2.5",
      lg: "gap-3",
    },
  },
  defaultVariants: {
    size: "default",
  },
});

// Ukuran ikon bintang per ukuran.
export const starVariants = cva("shrink-0", {
  variants: {
    size: {
      sm: "size-4",
      default: "size-5",
      lg: "size-6",
    },
  },
  defaultVariants: {
    size: "default",
  },
});

// Teks nilai (showValue). Hindari text-xs standalone -> text-xs sm:text-sm di sm.
export const valueVariants = cva("font-medium tracking-tight tabular-nums", {
  variants: {
    size: {
      sm: "text-xs sm:text-sm",
      default: "text-sm",
      lg: "text-base",
    },
  },
  defaultVariants: {
    size: "default",
  },
});

export type RatingVariants = VariantProps<typeof ratingVariants>;
