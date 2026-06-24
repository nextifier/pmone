import type { VariantProps } from "class-variance-authority";
import { cva } from "class-variance-authority";

export { default as Toggle } from "./Toggle.vue";

export const toggleVariants = cva(
  "inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium hover:bg-muted hover:text-muted-foreground disabled:pointer-events-none disabled:opacity-50 data-[state=on]:bg-accent data-[state=on]:text-accent-foreground [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 [&_svg]:shrink-0 focus-visible:border-ring focus-visible:ring-ring focus-visible:ring-[1px] outline-none transition-[color,box-shadow] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive whitespace-nowrap",
  {
    variants: {
      variant: {
        default: "bg-transparent",
        outline:
          "border border-border bg-transparent shadow-xs hover:bg-muted hover:text-foreground",
      },
      size: {
        default: "h-9 px-2 min-w-9",
        sm: "h-8 px-1.5 min-w-8",
        lg: "h-10 px-2.5 min-w-10",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
);

export type ToggleVariants = VariantProps<typeof toggleVariants>;

// Standalone "pill" and "card" surfaces used by the event-website pickers (day
// picker, session slots). They need a bordered, rounded-xl surface with a
// primary selected accent that doesn't fit the canonical segmented base, so
// they live outside the cva and are applied via `cn`. Shared here so both
// `Toggle` and `ToggleGroupItem` render the exact same pill.
export const togglePillClass =
  "inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-xl border border-border bg-background px-3 py-2 text-sm font-medium tracking-tight outline-none transition-[color,box-shadow] hover:bg-muted disabled:pointer-events-none disabled:opacity-50 focus-visible:border-ring focus-visible:ring-ring focus-visible:ring-[1px] data-[state=on]:border-primary data-[state=on]:bg-primary/5 data-[state=on]:text-foreground data-[state=on]:ring-1 data-[state=on]:ring-primary";

// Like the pill but without the fixed single-line layout — the consumer supplies
// its own inner layout (flex direction, padding, alignment), e.g. the session
// slot cards which stack a time, host line and availability.
export const toggleCardClass =
  "rounded-xl border border-border bg-background outline-none transition-[color,box-shadow] hover:bg-muted disabled:pointer-events-none disabled:opacity-50 focus-visible:border-ring focus-visible:ring-ring focus-visible:ring-[1px] data-[state=on]:border-primary data-[state=on]:bg-primary/5 data-[state=on]:text-foreground data-[state=on]:ring-1 data-[state=on]:ring-primary";
