<template>
  <div :class="cn('relative', props.class)" :style="containerStyle">
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
  </div>
</template>

<script setup lang="ts">
import { cn } from "@/lib/utils";
import type { HTMLAttributes } from "vue";

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
});

const borderWidthPx = ref(0);

const slots = useSlots();
const hasNotch = computed(() => !!slots.notch);
let __cnId = 0;
if (typeof window !== "undefined") {
  __cnId = (window as unknown as { __cnCounter?: number }).__cnCounter =
    ((window as unknown as { __cnCounter?: number }).__cnCounter ?? 0) + 1;
}
const clipId = `cn-clip-${__cnId}`;

const bodyEl = ref<HTMLElement | null>(null);
const width = ref(0);
const height = ref(0);

let resizeObserver: ResizeObserver | null = null;

const measureBody = () => {
  if (!bodyEl.value) return;
  const rect = bodyEl.value.getBoundingClientRect();
  width.value = rect.width;
  height.value = rect.height;
};

onMounted(() => {
  measureBody();
  if (bodyEl.value && typeof ResizeObserver !== "undefined") {
    resizeObserver = new ResizeObserver(measureBody);
    resizeObserver.observe(bodyEl.value);
  }
});

onBeforeUnmount(() => {
  resizeObserver?.disconnect();
  resizeObserver = null;
});

const sizePx = ref(0);
const gapPx = ref(0);
const radiusPx = ref(0);

const parsePx = (value: string): number => {
  if (typeof window === "undefined" || !value) return 0;
  const div = document.createElement("div");
  div.style.position = "absolute";
  div.style.visibility = "hidden";
  div.style.width = value;
  document.body.appendChild(div);
  const px = parseFloat(getComputedStyle(div).width);
  document.body.removeChild(div);
  return Number.isFinite(px) ? px : 0;
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

const containerStyle = computed<Record<string, string>>(() => ({
  "--cn-size": props.size,
  "--cn-gap": props.gap,
  "--cn-radius": props.radius,
}));

const isBottom = computed(() => props.position.startsWith("bottom"));
const isCenter = computed(() => props.position.endsWith("center"));
const hSide = computed(
  () => props.position.split("-")[1] as "left" | "center" | "right",
);

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
  const notchR = C;
  const OR = Math.max(0, Math.min(R, N - C, w - R - N, h - R - N));

  const p = props.position;

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

  const ORc = S / 2;
  const AxLeft = w / 2 - C - ORc;
  const AxRight = w / 2 + C + ORc;
  const JxLeft = w / 2 - C;
  const JxRight = w / 2 + C;

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

const bodyStyle = computed<Record<string, string>>(() => {
  if (hasNotch.value && pathD.value) {
    return {
      clipPath: `path('${pathD.value}')`,
      background: "transparent",
    };
  }
  if (hasNotch.value) {
    return { background: "transparent" };
  }
  const style: Record<string, string> = {
    borderRadius: "var(--cn-radius)",
    background: props.cardBg,
  };
  if (props.bordered) {
    style.boxShadow = `0 0 0 ${props.borderWidth} ${props.borderColor}`;
  }
  return style;
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
</script>
