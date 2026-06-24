<template>
  <div class="@container w-full">
    <svg :viewBox="`0 0 ${W} ${H}`" class="w-full" :style="{ height: 'auto' }">
      <defs v-if="mode === 'gradient'">
        <linearGradient :id="`${uid}-front`" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" :stop-color="color" stop-opacity="0.9" />
          <stop offset="100%" :stop-color="color" stop-opacity="0.7" />
        </linearGradient>
        <linearGradient :id="`${uid}-back`" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" :stop-color="color" stop-opacity="0.5" />
          <stop offset="100%" :stop-color="color" stop-opacity="0.3" />
        </linearGradient>
        <linearGradient :id="`${uid}-side`" x1="0" y1="0" x2="1" y2="0">
          <stop offset="0%" :stop-color="color" stop-opacity="0.6" />
          <stop offset="100%" :stop-color="color" stop-opacity="0.4" />
        </linearGradient>
        <linearGradient :id="`${uid}-top`" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" :stop-color="color" stop-opacity="0.7" />
          <stop offset="100%" :stop-color="color" stop-opacity="0.5" />
        </linearGradient>
      </defs>

      <g v-for="bar in bars" :key="bar.label">
        <!-- Back face -->
        <rect
          :x="bar.x + bar.depth"
          :y="bar.y - bar.depth"
          :width="barWidth"
          :height="bar.height"
          :fill="faceFill('back')"
          :opacity="faceOpacity('back')"
          rx="3"
        />
        <!-- Right side face -->
        <polygon :points="bar.sidePoints" :fill="faceFill('side')" :opacity="faceOpacity('side')" />
        <!-- Top face -->
        <polygon :points="bar.topPoints" :fill="faceFill('top')" :opacity="faceOpacity('top')" />
        <!-- Front face -->
        <rect
          :x="bar.x"
          :y="bar.y"
          :width="barWidth"
          :height="bar.height"
          :fill="faceFill('front')"
          rx="3"
        />
        <!-- Category label -->
        <text
          :x="bar.x + barWidth / 2"
          :y="H - 8"
          text-anchor="middle"
          class="fill-muted-foreground"
          style="font-size: 12px"
        >
          {{ bar.label }}
        </text>
      </g>
    </svg>
  </div>
</template>

<script setup>
let bar3dUid = 0;

const props = defineProps({
  data: {
    type: Array,
    required: true,
  },
  config: {
    type: Object,
    required: true,
  },
  xKey: {
    type: String,
    default: "month",
  },
  valueKey: {
    type: String,
    default: "value",
  },
  // gradient = four-face gradients (chart 6); solid = single color with opacity faces (chart 8).
  mode: {
    type: String,
    default: "gradient",
  },
  colorOverride: {
    type: String,
    default: null,
  },
  barCategoryGap: {
    type: Number,
    default: 0.2,
  },
});

const W = 520;
const H = 300;
const margin = { top: 40, right: 24, bottom: 28, left: 24 };
const innerW = W - margin.left - margin.right;
const innerH = H - margin.top - margin.bottom;

const uid = `bar3d-${(bar3dUid += 1)}`;

const color = computed(
  () => props.colorOverride || props.config[props.valueKey]?.color || "var(--chart-1)"
);

const band = computed(() => innerW / props.data.length);
const barWidth = computed(() => band.value * (1 - props.barCategoryGap));

const maxY = computed(() => Math.max(...props.data.map((d) => Number(d[props.valueKey]) || 0), 1));

const bars = computed(() =>
  props.data.map((d, i) => {
    const value = Number(d[props.valueKey]) || 0;
    const height = (value / maxY.value) * innerH;
    const x = margin.left + i * band.value + (band.value - barWidth.value) / 2;
    const y = margin.top + (innerH - height);
    const bw = barWidth.value;
    const depth = Math.min(bw * 0.3, 15);
    const sidePoints = [
      `${x + bw + 3},${y - 3}`,
      `${x + bw + depth - 3},${y - depth + 3}`,
      `${x + bw + depth - 3},${y + height - depth - 3}`,
      `${x + bw + 3},${y + height - 3}`,
    ].join(" ");
    const topPoints = [
      `${x + 3},${y - 3}`,
      `${x + bw - 3},${y - 3}`,
      `${x + bw + depth - 3},${y - depth + 3}`,
      `${x + depth + 3},${y - depth + 3}`,
    ].join(" ");
    return { label: d[props.xKey], x, y, height, depth, sidePoints, topPoints };
  })
);

function faceFill(face) {
  return props.mode === "gradient" ? `url(#${uid}-${face})` : color.value;
}

const SOLID_OPACITY = { back: 0.6, side: 0.7, top: 0.8, front: 1 };

function faceOpacity(face) {
  return props.mode === "gradient" ? 1 : SOLID_OPACITY[face];
}
</script>
