import type { VariantProps } from "class-variance-authority";
import { cva } from "class-variance-authority";

export { default as Rating } from "./Rating.vue";

// Container: jarak antar bintang per ukuran.
export const ratingVariants = cva("inline-flex items-center outline-none", {
  variants: {
    size: {
      sm: "gap-2.5",
      default: "gap-3",
      lg: "gap-3.5",
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
      sm: "size-5",
      default: "size-7",
      lg: "size-8",
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

// Warna bintang terisi. Dipakai jalur readonly + overlay nilai pecahan.
export const starColorVariants = cva("", {
  variants: {
    color: {
      yellow: "text-yellow-400",
      warning: "text-warning",
      primary: "text-foreground",
      foreground: "text-foreground",
    },
  },
  defaultVariants: {
    color: "yellow",
  },
});

// Versi data-state untuk RatingItemIndicator: transparan sampai step-nya aktif.
// reka menandai data-state="active" berdasarkan hoveredRating || modelValue,
// jadi kelas ini sekaligus menangani hover preview. Kelas ditulis literal supaya
// terbaca pemindai Tailwind.
export const starActiveColorVariants = cva("text-transparent", {
  variants: {
    color: {
      yellow: "data-[state=active]:text-yellow-400",
      warning: "data-[state=active]:text-warning",
      primary: "data-[state=active]:text-foreground",
      foreground: "data-[state=active]:text-foreground",
    },
  },
  defaultVariants: {
    color: "yellow",
  },
});

export type RatingVariants = VariantProps<typeof ratingVariants>;
export type RatingColorVariants = VariantProps<typeof starColorVariants>;
