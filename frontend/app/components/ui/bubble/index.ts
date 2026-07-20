import type { VariantProps } from "class-variance-authority";
import { cva } from "class-variance-authority";

export { default as BubbleGroup } from "./BubbleGroup.vue";
export { default as Bubble } from "./Bubble.vue";
export { default as BubbleContent } from "./BubbleContent.vue";
export { default as BubbleReactions } from "./BubbleReactions.vue";

export const bubbleVariants = cva(
  "group/bubble relative flex w-fit max-w-[80%] min-w-0 flex-col gap-1 text-sm tracking-tight data-[align=end]:items-end data-[align=end]:self-end",
  {
    variants: {
      // Colour targets the child content slots (not the root) so a single Bubble
      // can hold multiple <BubbleContent> blocks, each painted as its own pill.
      variant: {
        default:
          "*:data-[slot=bubble-content]:bg-primary *:data-[slot=bubble-content]:text-primary-foreground",
        secondary:
          "*:data-[slot=bubble-content]:bg-secondary *:data-[slot=bubble-content]:text-secondary-foreground",
        muted:
          "*:data-[slot=bubble-content]:bg-muted *:data-[slot=bubble-content]:text-foreground",
        tinted:
          "*:data-[slot=bubble-content]:bg-primary/10 *:data-[slot=bubble-content]:text-primary",
        outline:
          "*:data-[slot=bubble-content]:border *:data-[slot=bubble-content]:bg-transparent *:data-[slot=bubble-content]:text-foreground",
        ghost:
          "*:data-[slot=bubble-content]:bg-transparent *:data-[slot=bubble-content]:px-0 *:data-[slot=bubble-content]:py-0 *:data-[slot=bubble-content]:text-foreground",
        destructive:
          "*:data-[slot=bubble-content]:bg-destructive *:data-[slot=bubble-content]:text-white",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
);

export const bubbleReactionsVariants = cva(
  "absolute z-10 flex w-fit items-center justify-center gap-0.5 rounded-full border bg-background px-1 py-0.5 text-foreground shadow-xs",
  {
    variants: {
      side: {
        top: "bottom-full mb-1",
        bottom: "top-full mt-1",
      },
      align: {
        start: "start-2",
        end: "end-2",
      },
    },
    defaultVariants: {
      side: "bottom",
      align: "end",
    },
  }
);

export type BubbleVariants = VariantProps<typeof bubbleVariants>;
export type BubbleReactionsVariants = VariantProps<
  typeof bubbleReactionsVariants
>;
