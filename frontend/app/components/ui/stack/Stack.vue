<script setup lang="ts">
import { computed } from "vue";
import type { CSSProperties, HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";
import type { StackDirection, StackVariants } from ".";
import { stackThickness, stackVariants } from ".";

const props = withDefaults(
  defineProps<{
    class?: HTMLAttributes["class"];
    /** Recede direction / pose preset. */
    direction?: StackVariants["direction"];
    /** Number of stacked slabs. */
    layers?: number;
    /** Depth (scene-Z) spacing between adjacent layers, in px. */
    gap?: number;
    /** Total slab side depth in px (drives the outlined box-shadow thickness). */
    thickness?: number;
    /** Card edge length (any CSS length, e.g. "3rem"). */
    size?: string;
    /** Optional non-square layer width (overrides the square size). */
    width?: string;
    /** Optional layer aspect-ratio (e.g. "4 / 3"). */
    aspect?: string;
    /** Lift the layers apart on hover. */
    interactive?: boolean;
  }>(),
  {
    direction: "br-tl",
    layers: 3,
    gap: 59,
    thickness: 3,
    size: "3rem",
    interactive: true,
  },
);

const count = computed(() => Math.max(1, Math.floor(props.layers ?? 3)));

/**
 * b-t / t-b are FACE-ON stacked cards (front card biggest, cards behind shrink +
 * recede + fade) - a flat illustration, not the 3D rotated stack. Verbatim from
 * reui's "No products" (b-t) and "Looking for something?" (t-b) empty states.
 */
const isVertical = computed(() => props.direction === "b-t" || props.direction === "t-b");

const tdir = computed(
  () => stackThickness[(props.direction ?? "br-tl") as StackDirection] ?? stackThickness["br-tl"],
);

/**
 * The outlined slab side: card-coloured fills paint the wall (no staircase),
 * then 1px --border copies poke a continuous outline past the whole silhouette.
 * Offsets follow the variant's thickness direction (tx, ty) so the slab extrudes
 * toward the recede edge for ANY pose, not just the default upper-left.
 */
const boxShadow = computed(() => {
  const { tx, ty, depthScale } = tdir.value;
  const depthPx = (props.thickness ?? 3) * depthScale;
  // Fixed 0.5px sub-steps so the wall never staircases; the step COUNT scales with
  // depth so thick (vertical) slabs stay continuous and thin (diagonal) ones match.
  const steps = Math.max(2, Math.round(depthPx / 0.5));
  const off = (s: number) => `${(tx * 0.5 * s).toFixed(2)}px ${(ty * 0.5 * s).toFixed(2)}px`;
  const parts: string[] = [];
  for (let s = 1; s <= steps; s++) {
    parts.push(`${off(s)} 0 0 var(--card)`);
  }
  for (let s = 2; s <= steps; s++) {
    parts.push(`${off(s)} 0 1px var(--border)`);
  }
  return parts.join(", ");
});

const rootStyle = computed<CSSProperties>(() => ({
  "--st-size": props.size,
  "--st-box": `calc(${props.size} * 2.4)`,
  width: "var(--st-box)",
  height: "var(--st-box)",
}));

const layerClass = computed(() =>
  cn(
    "absolute top-1/2 left-1/2 flex items-center justify-center rounded-[14%] border border-border bg-card",
    "transition-[translate] duration-(--avatar-dur) ease-(--avatar-ease-in) motion-reduce:transition-none",
    "[--spread:0px]",
  ),
);

/**
 * Per-layer hover spread, as STATIC class values (no var*var in calc, which
 * untyped custom properties can't multiply). Front-side layers push toward the
 * viewer, back-side recede; the depth axis projects per the variant, so the
 * fan-apart follows the angle automatically.
 */
function layerHover(i: number): string {
  if (!props.interactive) {
    return "";
  }
  const s = signFor(i);
  if (s > 0) {
    return "group-hover/stack:[--spread:11px]";
  }
  if (s < 0) {
    return "group-hover/stack:[--spread:-11px]";
  }
  return "";
}

function zFor(i: number): number {
  return (i - (count.value - 1) / 2) * (props.gap ?? 59) * tdir.value.gapScale;
}

function signFor(i: number): number {
  return Math.sign(i - (count.value - 1) / 2);
}

/** Front (i = count-1) is solid; layers fade toward the back, floored at 0.5. */
function opacityFor(i: number): number {
  const c = count.value;
  if (c <= 1) {
    return 1;
  }
  const step = 0.4 / (c - 1);
  return Math.max(0.5, Number((1 - (c - 1 - i) * step).toFixed(3)));
}

function layerStyle(i: number): CSSProperties {
  const style: CSSProperties = {
    "--z": `${zFor(i)}px`,
    opacity: opacityFor(i),
    boxShadow: boxShadow.value,
    translate: "-50% -50% calc(var(--z) + var(--spread))",
    width: props.width ?? "var(--st-size)",
  };
  if (props.aspect) {
    style.aspectRatio = props.aspect;
  } else {
    style.height = "var(--st-size)";
  }
  return style;
}

/** Face-on (b-t/t-b): front (i = count-1) full size, each card behind shrinks. */
function verticalScale(i: number): number {
  return Number((1 - (count.value - 1 - i) * 0.13).toFixed(3));
}

/** Face-on vertical offset px: t-b front sits on top, b-t front sits at bottom. */
function verticalOffset(i: number): number {
  const dir = props.direction === "t-b" ? -1 : 1;
  return dir * (i - (count.value - 1) / 2) * (props.gap ?? 59) * tdir.value.gapScale;
}

function verticalLayerStyle(i: number): CSSProperties {
  return {
    opacity: opacityFor(i),
    width: "var(--st-size)",
    height: "var(--st-size)",
    scale: String(verticalScale(i)),
    translate: `-50% calc(-50% + ${verticalOffset(i)}px + var(--vspread, 0px))`,
  };
}

/**
 * Face-on lift-apart on hover (avatar-group-hover pattern): each card fans
 * further along the recede axis - front toward its edge, back away - then
 * settles back. Static per-layer values (no var*var). Reuses --avatar tokens.
 */
function verticalHover(i: number): string {
  if (!props.interactive) {
    return "";
  }
  const dir = props.direction === "t-b" ? -1 : 1;
  const s = Math.sign(dir * (i - (count.value - 1) / 2));
  if (s > 0) {
    return "group-hover/stack:[--vspread:7px]";
  }
  if (s < 0) {
    return "group-hover/stack:[--vspread:-7px]";
  }
  return "";
}
</script>

<template>
  <div
    data-slot="stack"
    :class="cn('group/stack relative flex shrink-0 items-center justify-center', props.class)"
    :style="rootStyle"
  >
    <!-- Vertical (b-t / t-b): face-on stack - front card biggest, cards behind
         shrink + offset + fade. Same card style as the diagonals (square, bg-card,
         border, outlined thickness, icon, opacity fade); only the pose differs. -->
    <div
      v-if="isVertical"
      class="relative size-(--st-size) translate-y-0 transition-[translate] duration-(--avatar-dur) ease-(--avatar-ease-in) motion-reduce:transition-none starting:translate-y-1"
    >
      <div
        v-for="i in count"
        :key="i"
        :class="
          cn(
            'absolute top-1/2 left-1/2 flex items-center justify-center rounded-[14%] border border-border bg-card',
            'transition-[translate] duration-(--avatar-dur) ease-(--avatar-ease-in) motion-reduce:transition-none [--vspread:0px]',
            i - 1 === count - 1 && 'shadow-sm',
            verticalHover(i - 1)
          )
        "
        :style="verticalLayerStyle(i - 1)"
        :aria-hidden="i - 1 !== count - 1 || undefined"
      >
        <slot :name="`layer-${count - (i - 1)}`">
          <slot v-if="i - 1 === count - 1" />
        </slot>
      </div>
    </div>

    <!-- Diagonal: 3D rotated isometric stack -->
    <div
      v-else
      :class="
        cn(
          stackVariants({ direction }),
          'size-(--st-size) translate-y-0',
          'transition-[translate] duration-(--avatar-dur) ease-(--avatar-ease-in) motion-reduce:transition-none',
          'starting:translate-y-1'
        )
      "
    >
      <div
        v-for="i in count"
        :key="i"
        :class="cn(layerClass, layerHover(i - 1))"
        :style="layerStyle(i - 1)"
        :aria-hidden="i - 1 !== count - 1 || undefined"
      >
        <slot :name="`layer-${count - (i - 1)}`">
          <slot v-if="i - 1 === count - 1" />
        </slot>
      </div>
    </div>
  </div>
</template>
