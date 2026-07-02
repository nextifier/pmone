import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Avatar } from "./Avatar.vue"
export { default as AvatarBadge } from "./AvatarBadge.vue"
export { default as AvatarFallback } from "./AvatarFallback.vue"
export { default as AvatarGroup } from "./AvatarGroup.vue"
export { default as AvatarGroupCount } from "./AvatarGroupCount.vue"
export { default as AvatarImage } from "./AvatarImage.vue"

// NOTE: sizes/rounding are baked into the cva here (not left to `.cn-avatar` in
// the style-*.css files) so the composite renders correctly under EVERY style —
// including `mono` (pmone's default), which has no `.cn-avatar` rule. Where a
// style does define `.cn-avatar`, the values match, so there is no conflict.
export const avatarVariants = cva(
  "cn-avatar group/avatar relative flex shrink-0 select-none overflow-hidden rounded-full after:absolute after:inset-0 after:rounded-full after:border after:border-border after:mix-blend-darken dark:after:mix-blend-lighten",
  {
    variants: {
      size: {
        sm: "size-6 text-xs",
        default: "size-8 text-sm",
        lg: "size-10 text-base",
      },
    },
    defaultVariants: {
      size: "default",
    },
  },
)

export type AvatarVariants = VariantProps<typeof avatarVariants>
