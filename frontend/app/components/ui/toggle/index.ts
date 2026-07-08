import type { VariantProps } from "class-variance-authority";
import { cva } from "class-variance-authority";

export { default as Toggle } from "./Toggle.vue";

export const toggleVariants = cva(
  "cn-toggle group/toggle hover:bg-muted inline-flex cursor-pointer items-center justify-center whitespace-nowrap outline-none focus-visible:ring-3 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0",
  {
    variants: {
      variant: {
        default: "cn-toggle-variant-default",
        outline: "cn-toggle-variant-outline",
      },
      size: {
        default: "cn-toggle-size-default",
        sm: "cn-toggle-size-sm",
        lg: "cn-toggle-size-lg",
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
  "inline-flex cursor-pointer items-center justify-center gap-1.5 whitespace-nowrap rounded-xl border border-border bg-background px-3 py-2 text-sm font-medium tracking-tight outline-none transition-[color,box-shadow] hover:bg-muted disabled:pointer-events-none disabled:opacity-50 focus-visible:border-ring focus-visible:ring-ring focus-visible:ring-[1px] data-[state=on]:border-primary data-[state=on]:bg-primary/5 data-[state=on]:text-foreground data-[state=on]:ring-1 data-[state=on]:ring-primary";

// Like the pill but without the fixed single-line layout — the consumer supplies
// its own inner layout (flex direction, padding, alignment), e.g. the session
// slot cards which stack a time, host line and availability.
export const toggleCardClass =
  "cursor-pointer rounded-xl border border-border bg-background outline-none transition-[color,box-shadow] hover:bg-muted disabled:pointer-events-none disabled:opacity-50 focus-visible:border-ring focus-visible:ring-ring focus-visible:ring-[1px] data-[state=on]:border-primary data-[state=on]:bg-primary/5 data-[state=on]:text-foreground data-[state=on]:ring-1 data-[state=on]:ring-primary";
