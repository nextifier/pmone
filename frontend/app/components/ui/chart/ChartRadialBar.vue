<template>
  <div class="@container flex w-full flex-col items-center">
    <div class="relative mx-auto aspect-square w-full max-w-[300px]">
      <svg :viewBox="`0 0 ${VB} ${VB}`" class="w-full">
        <defs>
          <linearGradient
            v-for="(ring, i) in rings"
            :id="ring.gradientId"
            :key="ring.gradientId"
            x1="0"
            y1="0"
            x2="1"
            y2="0"
          >
            <stop offset="0%" :stop-color="ring.color" stop-opacity="0.5" />
            <stop offset="100%" :stop-color="ring.color" stop-opacity="1" />
          </linearGradient>
          <filter :id="glowId" x="-15%" y="-15%" width="130%" height="130%">
            <feGaussianBlur stdDeviation="3" result="blur" />
            <feComposite in="SourceGraphic" in2="blur" operator="over" />
          </filter>
        </defs>

        <g :transform="`rotate(-90 ${cx} ${cy})`">
          <!-- Background tracks -->
          <template v-if="background">
            <circle
              v-for="ring in rings"
              :key="`track-${ring.name}`"
              :cx="cx"
              :cy="cy"
              :r="ring.radius"
              fill="none"
              class="stroke-muted"
              :stroke-width="barSize"
            />
          </template>
          <!-- Value arcs -->
          <circle
            v-for="ring in rings"
            :key="`bar-${ring.name}`"
            :cx="cx"
            :cy="cy"
            :r="ring.radius"
            fill="none"
            :stroke="gradient ? `url(#${ring.gradientId})` : ring.color"
            :stroke-width="barSize"
            stroke-linecap="round"
            :stroke-dasharray="`${ring.dash} ${ring.circumference}`"
            :filter="glow ? `url(#${glowId})` : undefined"
          />
        </g>

        <!-- Labels at each bar's start (12 o'clock) -->
        <template v-if="showLabels">
          <text
            v-for="ring in rings"
            :key="`label-${ring.name}`"
            :x="cx + 5"
            :y="cy - ring.radius"
            text-anchor="start"
            dominant-baseline="middle"
            fill="#fff"
            :style="{ fontSize: '11px', fontWeight: 600 }"
          >
            {{ ring.label }}
          </text>
        </template>
      </svg>
    </div>

    <div v-if="legend" class="flex flex-wrap items-center justify-center gap-4 pt-1">
      <div v-for="ring in rings" :key="`legend-${ring.name}`" class="flex items-center gap-1.5">
        <span class="size-2 shrink-0 rounded-[2px]" :style="{ backgroundColor: ring.color }" />
        <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">{{ ring.label }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
let radialUid = 0;

const props = defineProps({
  data: {
    type: Array,
    required: true,
  },
  config: {
    type: Object,
    required: true,
  },
  valueKey: {
    type: String,
    default: "value",
  },
  nameKey: {
    type: String,
    default: "name",
  },
  innerRadius: {
    type: Number,
    default: 35,
  },
  outerRadius: {
    type: Number,
    default: 110,
  },
  barSize: {
    type: Number,
    default: 22,
  },
  max: {
    type: Number,
    default: 100,
  },
  gradient: {
    type: Boolean,
    default: true,
  },
  glow: {
    type: Boolean,
    default: true,
  },
  background: {
    type: Boolean,
    default: true,
  },
  showLabels: {
    type: Boolean,
    default: true,
  },
  legend: {
    type: Boolean,
    default: true,
  },
});

const VB = 250;
const cx = VB / 2;
const cy = VB / 2;

const uid = `radial-${(radialUid += 1)}`;
const glowId = `${uid}-glow`;

const rings = computed(() => {
  const count = props.data.length;
  const band = props.outerRadius - props.innerRadius;
  const gap = count > 1 ? Math.max((band - count * props.barSize) / (count - 1), 2) : 0;

  return props.data.map((d, i) => {
    const name = d[props.nameKey];
    const value = Number(d[props.valueKey]) || 0;
    // First data row sits on the outermost ring.
    const radius = props.outerRadius - props.barSize / 2 - i * (props.barSize + gap);
    const circumference = 2 * Math.PI * radius;
    const frac = Math.min(value / props.max, 1);
    return {
      name,
      label: props.config[name]?.label || name,
      color: d.colorValue || props.config[name]?.color || "var(--chart-1)",
      radius,
      circumference,
      dash: frac * circumference,
      gradientId: `${uid}-grad-${i}`,
    };
  });
});
</script>
