import type { VariantProps } from "class-variance-authority";
import { cva } from "class-variance-authority";

export { default as Result } from "./Result.vue";
export { default as ResultActions } from "./ResultActions.vue";
export { default as ResultContent } from "./ResultContent.vue";
export { default as ResultDescription } from "./ResultDescription.vue";
export { default as ResultHeader } from "./ResultHeader.vue";
export { default as ResultMedia } from "./ResultMedia.vue";
export { default as ResultReference } from "./ResultReference.vue";
export { default as ResultTitle } from "./ResultTitle.vue";

/** Outcome semantics. Drives the media tint and the default glyph. */
export type ResultStatus = "success" | "pending" | "info" | "error";

/**
 * Default glyph per status. The `success` entry is only reached when a Result is
 * told to use an icon: left to itself, ResultMedia draws an inline <svg> check so
 * the stroke can animate (an Iconify-injected path has no length CSS can key a
 * stroke-dasharray to).
 */
export const resultStatusIcons: Record<ResultStatus, string> = {
  success: "hugeicons:checkmark-circle-02",
  pending: "hugeicons:clock-01",
  info: "hugeicons:information-circle",
  error: "hugeicons:alert-02",
};

/** Context Result hands down so parts written inside its slot inherit status/size. */
export const RESULT_CONTEXT = Symbol("resultContext");

export interface ResultContext {
  readonly status: ResultStatus;
  readonly variant: NonNullable<ResultMediaVariants["variant"]>;
  readonly size: NonNullable<ResultVariants["size"]>;
  readonly announce: boolean;
}

/**
 * Root layout only. No `min-h-*` on purpose: half the call sites are heroes at
 * the top of a scrolling page, not full-screen states, so the height belongs to
 * whatever wrapper the consumer owns.
 */
export const resultVariants = cva(
  "flex w-full min-w-0 flex-col items-center text-center text-balance",
  {
    variants: {
      size: {
        sm: "gap-3",
        default: "gap-4",
        lg: "gap-5",
      },
    },
    defaultVariants: {
      size: "default",
    },
  },
);

/**
 * `variant` is declared after `status` so its background wins in twMerge:
 * `muted` repaints the circle neutral and `plain` drops it, while both keep
 * whatever the status decided about the glyph colour (except muted, which
 * states its own).
 */
export const resultMediaVariants = cva(
  "inline-flex shrink-0 items-center justify-center [&_svg]:pointer-events-none [&_svg]:shrink-0",
  {
    variants: {
      status: {
        success: "bg-success/15 text-success-foreground",
        pending: "bg-warning/15 text-warning-foreground",
        info: "bg-info/15 text-info-foreground",
        error: "bg-destructive/10 text-destructive-foreground",
      },
      variant: {
        soft: "rounded-full",
        muted: "bg-muted text-foreground rounded-full",
        plain: "bg-transparent",
      },
      size: {
        sm: "size-12 [&_svg]:size-6",
        default: "size-14 [&_svg]:size-7",
        lg: "size-16 [&_svg]:size-8",
      },
    },
    defaultVariants: {
      status: "success",
      variant: "soft",
      size: "default",
    },
  },
);

export type ResultVariants = VariantProps<typeof resultVariants>;
export type ResultMediaVariants = VariantProps<typeof resultMediaVariants>;
