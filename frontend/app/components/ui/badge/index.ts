import type { VariantProps } from "class-variance-authority";
import { cva } from "class-variance-authority";

export { default as Badge } from "./Badge.vue";

export const badgeVariants = cva(
  "inline-flex w-fit shrink-0 items-center gap-x-1.5 text-foreground whitespace-nowrap font-normal tracking-tight text-sm",
  {
    variants: {
      plain: {
        true: "",
        false: "rounded-full border border-foreground/17 px-2 py-1",
      },
    },
    defaultVariants: { plain: false },
  }
);

export const badgeDotVariants = cva("size-2 shrink-0 rounded-full", {
  variants: {
    variant: {
      default: "bg-primary",
      info: "bg-info",
      success: "bg-success",
      warning: "bg-warning",
      destructive: "bg-destructive",
      muted: "bg-foreground/30",
      outline: "",
    },
  },
  defaultVariants: { variant: "default" },
});

export const badgeIconVariants = cva("size-4 shrink-0", {
  variants: {
    variant: {
      default: "text-foreground",
      info: "text-info-foreground",
      success: "text-success-foreground",
      warning: "text-warning-foreground",
      destructive: "text-destructive-foreground",
      muted: "text-muted-foreground",
      outline: "text-foreground",
    },
  },
  defaultVariants: { variant: "default" },
});

export type BadgeVariants = VariantProps<typeof badgeDotVariants>;

export type BadgeVariant = NonNullable<BadgeVariants["variant"]>;

export const badgeDefaultIcons: Record<BadgeVariant, string> = {
  default: "lucide:circle-dot-dashed",
  info: "lucide:info",
  success: "lucide:circle-check-big",
  warning: "lucide:triangle-alert",
  destructive: "lucide:circle-x",
  muted: "lucide:circle-minus",
  outline: "lucide:circle-dashed",
};
