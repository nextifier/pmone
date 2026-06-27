import type { VariantProps } from "class-variance-authority";
import { cva } from "class-variance-authority";

export { default as Marker } from "./Marker.vue";
export { default as MarkerIcon } from "./MarkerIcon.vue";
export { default as MarkerContent } from "./MarkerContent.vue";

export const markerVariants = cva(
  "group/marker relative flex w-full items-center gap-2 text-sm tracking-tight text-muted-foreground",
  {
    variants: {
      variant: {
        default: "justify-center text-center",
        separator:
          "justify-center before:h-px before:flex-1 before:bg-border before:content-[''] after:h-px after:flex-1 after:bg-border after:content-['']",
        border: "rounded-lg border bg-card px-3 py-2",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
);

export type MarkerVariants = VariantProps<typeof markerVariants>;
