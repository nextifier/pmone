<template>
  <component
    :is="rootTag"
    v-bind="rootBindings"
    :class="rootClass"
    :style="containerStyle"
  >
    <svg
      v-if="hasNotch && pathD"
      :width="width"
      :height="height"
      :viewBox="`0 0 ${width} ${height}`"
      class="pointer-events-none absolute inset-0"
      aria-hidden="true"
      shape-rendering="geometricPrecision"
    >
      <defs>
        <clipPath :id="clipId">
          <path :d="pathD" />
        </clipPath>
      </defs>
      <path :d="pathD" :fill="props.cardBg" />
      <path
        v-if="props.bordered"
        :d="pathD"
        fill="none"
        :stroke="props.borderColor"
        :stroke-width="borderWidthPx * 2"
        :clip-path="`url(#${clipId})`"
      />
    </svg>

    <div
      ref="bodyEl"
      :class="cn('text-card-foreground relative', bodyClass)"
      :style="bodyStyle"
    >
      <slot />
    </div>

    <div v-if="hasNotch" :style="notchStyle">
      <div
        class="flex size-full items-center justify-center rounded-full"
        :style="notchInnerStyle"
      >
        <slot name="notch" />
      </div>
    </div>
  </component>
</template>

<script setup lang="ts">
import { cn } from "@/lib/utils";
import { useId } from "reka-ui";
import { computed, onBeforeUnmount, onMounted, ref, resolveComponent, useSlots, watch } from "vue";
import type { HTMLAttributes } from "vue";
import type { RouteLocationRaw } from "vue-router";

type Position =
  | "top-left"
  | "top-center"
  | "top-right"
  | "bottom-left"
  | "bottom-center"
  | "bottom-right";

export interface CardNotchProps {
  class?: HTMLAttributes["class"];
  bodyClass?: HTMLAttributes["class"];
  position?: Position;
  size?: string;
  gap?: string;
  radius?: string;
  bordered?: boolean;
  borderColor?: string;
  borderWidth?: string;
  cardBg?: string;
  notched?: boolean;
  autoPad?: boolean;
  notchPadding?: string;
  to?: RouteLocationRaw | string;
  href?: string;
  as?: "div" | "button" | "a";
  interactive?: boolean;
  target?: string;
  rel?: string;
}

const props = withDefaults(defineProps<CardNotchProps>(), {
  position: "bottom-right",
  size: "3.5rem",
  gap: "8px",
  radius: "1.5rem",
  bordered: true,
  borderColor: "var(--color-primary)",
  borderWidth: "1px",
  cardBg: "var(--color-card)",
  autoPad: false,
  notchPadding: "0.75rem",
});

const slots = useSlots();
const hasNotch = computed(() => props.notched || !!slots.notch);

const clipId = `cn-clip-${useId().replace(/:/g, "")}`;

const bodyEl = ref<HTMLElement | null>(null);
const width = ref(0);
const height = ref(0);

let resizeObserver: ResizeObserver | null = null;
let rafId = 0;

const measureBody = () => {
  if (!bodyEl.value) return;
  const rect = bodyEl.value.getBoundingClientRect();
  width.value = rect.width;
  height.value = rect.height;
};

const scheduleMeasure = () => {
  if (rafId) return;
  rafId = requestAnimationFrame(() => {
    rafId = 0;
    measureBody();
  });
};

onMounted(() => {
  measureBody();
  if (bodyEl.value && typeof ResizeObserver !== "undefined") {
    resizeObserver = new ResizeObserver(scheduleMeasure);
    resizeObserver.observe(bodyEl.value);
  }
});

onBeforeUnmount(() => {
  resizeObserver?.disconnect();
  resizeObserver = null;
  if (rafId) cancelAnimationFrame(rafId);
});

const sizePx = ref(0);
const gapPx = ref(0);
const radiusPx = ref(0);
const borderWidthPx = ref(0);

const pxLiteral = /^-?\d+(\.\d+)?px$/;
const pxCache = new Map<string, number>();

const parsePx = (value: string): number => {
  if (!value) return 0;
  if (pxLiteral.test(value)) return parseFloat(value);
  if (typeof window === "undefined") return 0;
  const cached = pxCache.get(value);
  if (cached !== undefined) return cached;
  const div = document.createElement("div");
  div.style.position = "absolute";
  div.style.visibility = "hidden";
  div.style.width = value;
  document.body.appendChild(div);
  const px = parseFloat(getComputedStyle(div).width);
  document.body.removeChild(div);
  const resolved = Number.isFinite(px) ? px : 0;
  pxCache.set(value, resolved);
  return resolved;
};

const updateMeasurements = () => {
  sizePx.value = parsePx(props.size);
  gapPx.value = parsePx(props.gap);
  radiusPx.value = parsePx(props.radius);
  borderWidthPx.value = parsePx(props.borderWidth);
};

onMounted(updateMeasurements);
watch(
  () => [props.size, props.gap, props.radius, props.borderWidth],
  updateMeasurements,
);

const isBottom = computed(() => props.position.startsWith("bottom"));
const isCenter = computed(() => props.position.endsWith("center"));
const hSide = computed(
  () => props.position.split("-")[1] as "left" | "center" | "right",
);

const autoPadValue = computed(() =>
  props.autoPad && hasNotch.value
    ? `calc(${props.size} + ${props.gap} + ${props.notchPadding})`
    : "0px",
);

const containerStyle = computed<Record<string, string>>(() => ({
  "--cn-size": props.size,
  "--cn-gap": props.gap,
  "--cn-radius": props.radius,
}));

/**
 * SVG path geometry (origin top-left, +x right, +y down):
 *   S  = notch diameter (sizePx)
 *   G  = gap between card edge and notch circle (gapPx)
 *   R  = card outer corner radius, clamped to min(w,h)/2
 *   N  = S + G (total offset along notch-bearing axes)
 *   C  = S/2 + G (radius of arc carved into card to clear the notch)
 *   OR = outer corner radius at the notch side, clamped so paths don't overlap
 *
 * Paths trace the full outline clockwise, subbing a concave C-radius arc
 * where the notch sits. Corners adjacent to the notch use OR instead of R
 * so they shrink gracefully when the card is tight.
 *
 * The *-center variants share ORc (S/2) plus symmetric join points
 * (Ax/Jx Left/Right) around the card's horizontal midline.
 */
const pathD = computed(() => {
  const w = width.value;
  const h = height.value;
  const S = sizePx.value;
  const G = gapPx.value;
  const Rraw = radiusPx.value;

  if (!hasNotch.value || !w || !h || !S) return "";

  const N = S + G;
  const C = S / 2 + G;
  const R = Math.min(Rraw, Math.min(w, h) / 2);
  const OR = Math.max(0, Math.min(R, N - C, w - R - N, h - R - N));

  const p = props.position;

  // bottom-right: start top-left, run clockwise, carve notch in bottom-right zone.
  if (p === "bottom-right") {
    return (
      `M ${R} 0 H ${w - R} A ${R} ${R} 0 0 1 ${w} ${R} ` +
      `V ${h - N - OR} A ${OR} ${OR} 0 0 1 ${w - OR} ${h - N} ` +
      `H ${w - N + C} A ${C} ${C} 0 0 0 ${w - N} ${h - N + C} ` +
      `V ${h - OR} A ${OR} ${OR} 0 0 1 ${w - N - OR} ${h} ` +
      `H ${R} A ${R} ${R} 0 0 1 0 ${h - R} ` +
      `V ${R} A ${R} ${R} 0 0 1 ${R} 0 Z`
    );
  }

  // bottom-left: clockwise, notch carved in bottom-left zone.
  if (p === "bottom-left") {
    return (
      `M ${R} 0 H ${w - R} A ${R} ${R} 0 0 1 ${w} ${R} ` +
      `V ${h - R} A ${R} ${R} 0 0 1 ${w - R} ${h} ` +
      `H ${N + OR} A ${OR} ${OR} 0 0 1 ${N} ${h - OR} ` +
      `V ${h - N + C} A ${C} ${C} 0 0 0 ${N - C} ${h - N} ` +
      `H ${OR} A ${OR} ${OR} 0 0 1 0 ${h - N - OR} ` +
      `V ${R} A ${R} ${R} 0 0 1 ${R} 0 Z`
    );
  }

  // top-right: clockwise, notch carved in top-right zone.
  if (p === "top-right") {
    return (
      `M ${R} 0 H ${w - N - OR} A ${OR} ${OR} 0 0 1 ${w - N} ${OR} ` +
      `V ${N - C} A ${C} ${C} 0 0 0 ${w - N + C} ${N} ` +
      `H ${w - OR} A ${OR} ${OR} 0 0 1 ${w} ${N + OR} ` +
      `V ${h - R} A ${R} ${R} 0 0 1 ${w - R} ${h} ` +
      `H ${R} A ${R} ${R} 0 0 1 0 ${h - R} ` +
      `V ${R} A ${R} ${R} 0 0 1 ${R} 0 Z`
    );
  }

  // top-left: clockwise starting past the notch, loop around, carve top-left.
  if (p === "top-left") {
    return (
      `M ${N + OR} 0 H ${w - R} A ${R} ${R} 0 0 1 ${w} ${R} ` +
      `V ${h - R} A ${R} ${R} 0 0 1 ${w - R} ${h} ` +
      `H ${R} A ${R} ${R} 0 0 1 0 ${h - R} ` +
      `V ${N + OR} A ${OR} ${OR} 0 0 1 ${OR} ${N} ` +
      `H ${N - C} A ${C} ${C} 0 0 0 ${N} ${N - C} ` +
      `V ${OR} A ${OR} ${OR} 0 0 1 ${N + OR} 0 Z`
    );
  }

  // *-center shared geometry: join points & outer radius around midline.
  const ORc = S / 2;
  const AxLeft = w / 2 - C - ORc;
  const AxRight = w / 2 + C + ORc;
  const JxLeft = w / 2 - C;
  const JxRight = w / 2 + C;

  // top-center: two outer arcs flank a concave notch arc at top midline.
  if (p === "top-center") {
    const Jy = S / 2;
    return (
      `M ${R} 0 H ${AxLeft} ` +
      `A ${ORc} ${ORc} 0 0 1 ${JxLeft} ${Jy} ` +
      `A ${C} ${C} 0 0 0 ${JxRight} ${Jy} ` +
      `A ${ORc} ${ORc} 0 0 1 ${AxRight} 0 ` +
      `H ${w - R} A ${R} ${R} 0 0 1 ${w} ${R} ` +
      `V ${h - R} A ${R} ${R} 0 0 1 ${w - R} ${h} ` +
      `H ${R} A ${R} ${R} 0 0 1 0 ${h - R} ` +
      `V ${R} A ${R} ${R} 0 0 1 ${R} 0 Z`
    );
  }

  // bottom-center: mirror of top-center along bottom midline.
  if (p === "bottom-center") {
    const Jy = h - S / 2;
    return (
      `M ${R} 0 H ${w - R} A ${R} ${R} 0 0 1 ${w} ${R} ` +
      `V ${h - R} A ${R} ${R} 0 0 1 ${w - R} ${h} ` +
      `H ${AxRight} ` +
      `A ${ORc} ${ORc} 0 0 1 ${JxRight} ${Jy} ` +
      `A ${C} ${C} 0 0 0 ${JxLeft} ${Jy} ` +
      `A ${ORc} ${ORc} 0 0 1 ${AxLeft} ${h} ` +
      `H ${R} A ${R} ${R} 0 0 1 0 ${h - R} ` +
      `V ${R} A ${R} ${R} 0 0 1 ${R} 0 Z`
    );
  }

  return "";
});

const fallbackCardStyle = (): Record<string, string> => {
  const style: Record<string, string> = {
    borderRadius: "var(--cn-radius)",
    background: props.cardBg,
  };
  if (props.bordered) {
    style.boxShadow = `0 0 0 ${props.borderWidth} ${props.borderColor}`;
  }
  return style;
};

const bodyStyle = computed<Record<string, string>>(() => {
  const pad: Record<string, string> =
    props.autoPad && hasNotch.value
      ? { [isBottom.value ? "paddingBottom" : "paddingTop"]: autoPadValue.value }
      : {};
  if (hasNotch.value && pathD.value) {
    return {
      ...pad,
      clipPath: `path('${pathD.value}')`,
      background: "transparent",
    };
  }
  if (hasNotch.value) {
    return { ...pad, ...fallbackCardStyle() };
  }
  return { ...pad, ...fallbackCardStyle() };
});

const notchInnerStyle = computed<Record<string, string>>(() => {
  const style: Record<string, string> = { background: props.cardBg };
  if (props.bordered) {
    style.boxShadow = `0 0 0 ${props.borderWidth} ${props.borderColor}`;
  }
  return style;
});

const notchStyle = computed<Record<string, string>>(() => {
  const style: Record<string, string> = {
    position: "absolute",
    width: "var(--cn-size)",
    height: "var(--cn-size)",
    zIndex: "2",
  };
  if (isBottom.value) style.bottom = "0";
  else style.top = "0";
  if (isCenter.value) {
    style.left = "50%";
    style.transform = "translateX(-50%)";
  } else if (hSide.value === "left") {
    style.left = "0";
  } else {
    style.right = "0";
  }
  return style;
});

const isLink = computed(() => props.to !== undefined || props.href !== undefined);
const isButton = computed(() => props.as === "button");
const isInteractive = computed(
  () => props.interactive || isLink.value || isButton.value,
);

const externalPattern = /^(https?:)?\/\//;
const isExternal = computed(() => {
  const target = typeof props.to === "string" ? props.to : props.href;
  return typeof target === "string" && externalPattern.test(target);
});

const NuxtLink = resolveComponent("NuxtLink");

const rootTag = computed(() => {
  if (isLink.value) return NuxtLink;
  if (isButton.value) return "button";
  return props.as ?? "div";
});

const rootBindings = computed<Record<string, unknown>>(() => {
  const bindings: Record<string, unknown> = {};
  if (isLink.value) {
    if (props.to !== undefined) bindings.to = props.to;
    if (props.href !== undefined) bindings.href = props.href;
    if (isExternal.value) {
      bindings.target = props.target ?? "_blank";
      bindings.rel = props.rel ?? "noopener noreferrer";
    } else {
      if (props.target !== undefined) bindings.target = props.target;
      if (props.rel !== undefined) bindings.rel = props.rel;
    }
  } else if (isButton.value) {
    bindings.type = "button";
  }
  return bindings;
});

const rootClass = computed(() =>
  cn(
    "relative block",
    isInteractive.value &&
      "cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:rounded-[var(--cn-radius)]",
    isButton.value && "w-full text-left",
    props.class,
  ),
);
</script>
