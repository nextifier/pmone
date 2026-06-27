import type { VariantProps } from "class-variance-authority";
import { cva } from "class-variance-authority";

export { default as BubbleGroup } from "./BubbleGroup.vue";
export { default as Bubble } from "./Bubble.vue";
export { default as BubbleContent } from "./BubbleContent.vue";
export { default as BubbleReactions } from "./BubbleReactions.vue";

export const bubbleVariants = cva(
  "group/bubble relative flex w-fit min-w-0 flex-col rounded-xl text-sm tracking-tight",
  {
    variants: {
      variant: {
        default: "bg-primary text-primary-foreground",
        secondary: "bg-secondary text-secondary-foreground",
        muted: "bg-muted text-foreground",
        tinted: "bg-primary/10 text-primary",
        outline: "border bg-transparent text-foreground",
        ghost:
          "bg-transparent text-foreground [&_[data-slot=bubble-content]]:px-0 [&_[data-slot=bubble-content]]:py-0",
        destructive: "bg-destructive text-white",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
);

export const bubbleReactionsVariants = cva(
  "absolute z-10 flex w-fit items-center justify-center",
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
