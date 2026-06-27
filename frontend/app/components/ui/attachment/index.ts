import type { VariantProps } from "class-variance-authority";
import { cva } from "class-variance-authority";

export { default as Attachment } from "./Attachment.vue";
export { default as AttachmentMedia } from "./AttachmentMedia.vue";
export { default as AttachmentContent } from "./AttachmentContent.vue";
export { default as AttachmentTitle } from "./AttachmentTitle.vue";
export { default as AttachmentDescription } from "./AttachmentDescription.vue";
export { default as AttachmentActions } from "./AttachmentActions.vue";
export { default as AttachmentAction } from "./AttachmentAction.vue";
export { default as AttachmentTrigger } from "./AttachmentTrigger.vue";
export { default as AttachmentGroup } from "./AttachmentGroup.vue";

export const attachmentVariants = cva(
  "group/attachment relative flex max-w-full min-w-0 shrink-0 flex-wrap border bg-card text-card-foreground transition-colors has-[>a,>button]:hover:bg-muted/50 data-[state=error]:border-destructive/30 data-[state=idle]:border-dashed",
  {
    variants: {
      size: {
        default: "gap-3 rounded-xl p-3",
        sm: "gap-2.5 rounded-lg p-2.5",
        xs: "gap-2 rounded-lg p-2",
      },
      orientation: {
        horizontal: "items-center",
        vertical: "flex-col",
      },
    },
    defaultVariants: {
      size: "default",
      orientation: "horizontal",
    },
  }
);

export const attachmentMediaVariants = cva(
  "relative flex aspect-square shrink-0 items-center justify-center overflow-hidden rounded-md bg-muted group-data-[state=error]/attachment:bg-destructive/10 group-data-[state=error]/attachment:text-destructive [&_svg]:pointer-events-none",
  {
    variants: {
      variant: {
        icon: "size-10 [&_svg:not([class*='size-'])]:size-5",
        image: "size-10 *:[img]:aspect-square *:[img]:size-full *:[img]:object-cover",
      },
    },
    defaultVariants: {
      variant: "icon",
    },
  }
);

export type AttachmentVariants = VariantProps<typeof attachmentVariants>;
export type AttachmentMediaVariants = VariantProps<
  typeof attachmentMediaVariants
>;
