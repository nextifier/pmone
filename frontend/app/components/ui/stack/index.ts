import type { VariantProps } from "class-variance-authority";
import { cva } from "class-variance-authority";

export { default as Stack } from "./Stack.vue";

/**
 * Scene pose per direction. Orthographic isometric (no perspective): the front
 * layer (brightest, highest --z) points toward the FROM edge; the stack recedes
 * toward the TO edge. Mirror logic: flipping rotateY sign mirrors left<->right,
 * flipping rotateX sign mirrors top<->bottom, skewY follows rotateY.
 */
export const stackVariants = cva("relative transform-3d", {
  variants: {
    direction: {
      "br-tl": "-rotate-x-12 rotate-y-24 -skew-y-24",
      "bl-tr": "-rotate-x-12 -rotate-y-24 skew-y-24",
      "tl-br": "rotate-x-12 -rotate-y-24 -skew-y-24",
      "tr-bl": "rotate-x-12 rotate-y-24 skew-y-24",
      "b-t": "[transform:rotateX(-34deg)_skewX(-9deg)_skewY(5deg)]",
      "t-b": "[transform:rotateX(34deg)_skewX(-9deg)_skewY(-5deg)]",
    },
  },
  defaultVariants: {
    direction: "br-tl",
  },
});

export type StackVariants = VariantProps<typeof stackVariants>;
export type StackDirection = NonNullable<StackVariants["direction"]>;

/**
 * In-plane thickness offset direction per variant. The slab side extrudes along
 * the projected scene-Z (recede) axis; under these mirror/axis-aligned poses the
 * local (tx, ty) offset projects to the correct screen edge. Unit-ish vectors:
 * the box-shadow magnitude is supplied separately by the `thickness` depth scale.
 */
export const stackThickness: Record<
  StackDirection,
  { tx: number; ty: number; gapScale: number; depthScale: number }
> = {
  "br-tl": { tx: -1, ty: -1, gapScale: 1, depthScale: 1 },
  "bl-tr": { tx: 1, ty: -1, gapScale: 1, depthScale: 1 },
  "tl-br": { tx: 1, ty: 1, gapScale: 1, depthScale: 1 },
  "tr-bl": { tx: -1, ty: 1, gapScale: 1, depthScale: 1 },
  // Vertical poses are rotateX-only (upright content, straight recede). They read
  // as the reference "card on a stack of slabs" via deeper thickness (depthScale)
  // + tight stacking, not a fancier pose.
  "b-t": { tx: 0, ty: -1, gapScale: 0.58, depthScale: 3 },
  "t-b": { tx: 0, ty: 1, gapScale: 0.58, depthScale: 3 },
};
