<template>
  <div class="@container w-full">
    <svg
      :viewBox="`0 0 ${W} ${H}`"
      class="w-full"
      @mouseleave="setActive(null)"
    >
      <g
        v-for="bar in bars"
        :key="bar.label"
        @mouseenter="setActive(bar.index)"
      >
        <rect
          :x="bar.active ? bar.fullX : bar.collapsedX"
          :y="bar.y"
          :width="bar.active ? barWidth : collapsedWidth"
          :height="bar.height"
          :fill="color"
          rx="3"
          :style="{ transition: 'x 0.5s cubic-bezier(0.34, 1.56, 0.64, 1), width 0.5s cubic-bezier(0.34, 1.56, 0.64, 1)' }"
        />
        <text
          v-if="bar.active"
          :x="bar.center"
          :y="bar.y - 6"
          text-anchor="middle"
          :fill="color"
          class="font-mono tabular-nums chart-animated-value"
          style="font-size: 13px; font-weight: 500"
        >
          {{ bar.value }}
        </text>
        <!-- Invisible hit area so thin/collapsed bars stay hoverable -->
        <rect
          :x="bar.center - band / 2"
          :y="margin.top"
          :width="band"
          :height="innerH"
          fill="transparent"
        />
      </g>
    </svg>
  </div>
</template>

<script setup>
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
  colorOverride: {
    type: String,
    default: null,
  },
});

const emit = defineEmits(["update:active"]);

const W = 520;
const H = 280;
const margin = { top: 24, right: 12, bottom: 24, left: 12 };
const innerW = W - margin.left - margin.right;
const innerH = H - margin.top - margin.bottom;
const collapsedWidth = 6;

const color = computed(
  () => props.colorOverride || props.config[props.valueKey]?.color || "var(--chart-1)"
);

const activeIndex = ref(null);

const band = computed(() => innerW / props.data.length);
const barWidth = computed(() => band.value * 0.7);
const maxY = computed(() => Math.max(...props.data.map((d) => Number(d[props.valueKey]) || 0), 1));

const bars = computed(() =>
  props.data.map((d, i) => {
    const value = Number(d[props.valueKey]) || 0;
    const height = (value / maxY.value) * innerH;
    const center = margin.left + i * band.value + band.value / 2;
    const y = margin.top + (innerH - height);
    return {
      index: i,
      label: d[props.xKey],
      value,
      center,
      y,
      height,
      fullX: center - barWidth.value / 2,
      collapsedX: center - collapsedWidth / 2,
      active: activeIndex.value === i,
    };
  })
);

function setActive(i) {
  activeIndex.value = i;
  emit("update:active", i === null ? null : props.data[i][props.valueKey]);
}
</script>

<style scoped>
.chart-animated-value {
  animation: chart-animated-value-in 0.2s ease-out;
}

@keyframes chart-animated-value-in {
  from {
    opacity: 0;
    transform: translateY(-6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
