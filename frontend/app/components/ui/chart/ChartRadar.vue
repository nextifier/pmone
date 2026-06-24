<template>
  <div class="@container flex w-full flex-col items-center">
    <div class="relative mx-auto aspect-square w-full max-w-[280px]">
      <svg :viewBox="`0 0 ${VB} ${VB}`" class="w-full overflow-visible">
        <defs>
          <linearGradient
            :id="fillId"
            x1="0"
            y1="0"
            :x2="gradientDirection === 'diagonal' ? 1 : 0"
            y2="1"
          >
            <stop offset="0%" :stop-color="seriesColor" :stop-opacity="fillTop" />
            <stop offset="100%" :stop-color="seriesColor" :stop-opacity="fillBottom" />
          </linearGradient>
          <filter :id="glowId" x="-20%" y="-20%" width="140%" height="140%">
            <feGaussianBlur :stdDeviation="glowStdDeviation" result="blur" />
            <feComposite in="SourceGraphic" in2="blur" operator="over" />
          </filter>
        </defs>

        <!-- Grid -->
        <g class="text-border">
          <polygon
            v-for="(poly, i) in gridPolygons"
            :key="`grid-${i}`"
            :points="poly"
            fill="none"
            stroke="currentColor"
            stroke-width="1"
            stroke-dasharray="3 3"
            opacity="0.7"
          />
          <line
            v-for="(spoke, i) in spokes"
            :key="`spoke-${i}`"
            :x1="cx"
            :y1="cy"
            :x2="spoke.x"
            :y2="spoke.y"
            stroke="currentColor"
            stroke-width="1"
            stroke-dasharray="3 3"
            opacity="0.7"
          />
        </g>

        <!-- Series area -->
        <path
          :d="areaPath"
          :fill="gradient ? `url(#${fillId})` : seriesColor"
          :fill-opacity="gradient ? 1 : 0.25"
          :stroke="seriesColor"
          :stroke-width="strokeWidth"
          stroke-linejoin="round"
          :filter="glow ? `url(#${glowId})` : undefined"
        />

        <!-- Dots -->
        <template v-if="dots">
          <circle
            v-for="(v, i) in vertices"
            :key="`dot-${i}`"
            :cx="v.x"
            :cy="v.y"
            r="4"
            :fill="dotVariant === 'ring' ? 'var(--background)' : seriesColor"
            :stroke="dotVariant === 'ring' ? seriesColor : 'none'"
            :stroke-width="dotVariant === 'ring' ? 2.5 : 0"
          />
        </template>

        <!-- Category labels -->
        <text
          v-for="(label, i) in axisLabels"
          :key="`label-${i}`"
          :x="label.x"
          :y="label.y"
          :text-anchor="label.anchor"
          :dominant-baseline="label.baseline"
          class="fill-muted-foreground"
          :style="{ fontSize: `${labelFontSize}px` }"
        >
          {{ label.text }}
        </text>
      </svg>
    </div>
  </div>
</template>

<script setup>
let radarUid = 0;

const props = defineProps({
  data: {
    type: Array,
    required: true,
  },
  config: {
    type: Object,
    required: true,
  },
  dataKey: {
    type: String,
    default: "value",
  },
  categoryKey: {
    type: String,
    default: "category",
  },
  maxDomain: {
    type: Number,
    default: 100,
  },
  gridLevels: {
    type: Number,
    default: 5,
  },
  gradient: {
    type: Boolean,
    default: true,
  },
  // vertical (top→bottom) or diagonal (top-left→bottom-right).
  gradientDirection: {
    type: String,
    default: "vertical",
  },
  fillTop: {
    type: Number,
    default: 0.5,
  },
  fillBottom: {
    type: Number,
    default: 0.08,
  },
  glow: {
    type: Boolean,
    default: true,
  },
  glowStdDeviation: {
    type: Number,
    default: 6,
  },
  dots: {
    type: Boolean,
    default: true,
  },
  // solid = filled dot; ring = background fill with colored stroke.
  dotVariant: {
    type: String,
    default: "solid",
  },
  strokeWidth: {
    type: Number,
    default: 2,
  },
  labelFontSize: {
    type: Number,
    default: 12,
  },
});

const VB = 250;
const cx = VB / 2;
const cy = VB / 2;
const maxR = 88;
const labelR = maxR + 16;

const uid = `radar-${(radarUid += 1)}`;
const fillId = `${uid}-fill`;
const glowId = `${uid}-glow`;

const seriesColor = computed(() => props.config[props.dataKey]?.color || "var(--chart-1)");

const n = computed(() => props.data.length);

function angleAt(i) {
  return -Math.PI / 2 + (i * 2 * Math.PI) / n.value;
}

function pointAt(i, radius) {
  const a = angleAt(i);
  return { x: cx + radius * Math.cos(a), y: cy + radius * Math.sin(a) };
}

const vertices = computed(() =>
  props.data.map((d, i) => {
    const value = Number(d[props.dataKey]) || 0;
    const r = maxR * Math.min(value / props.maxDomain, 1);
    return pointAt(i, r);
  })
);

const areaPath = computed(() => {
  if (!vertices.value.length) {
    return "";
  }
  return (
    vertices.value
      .map((v, i) => `${i === 0 ? "M" : "L"}${v.x.toFixed(2)},${v.y.toFixed(2)}`)
      .join(" ") + " Z"
  );
});

const gridPolygons = computed(() =>
  Array.from({ length: props.gridLevels }, (_, level) => {
    const r = (maxR * (level + 1)) / props.gridLevels;
    return props.data
      .map((_d, i) => {
        const p = pointAt(i, r);
        return `${p.x.toFixed(2)},${p.y.toFixed(2)}`;
      })
      .join(" ");
  })
);

const spokes = computed(() => props.data.map((_d, i) => pointAt(i, maxR)));

const axisLabels = computed(() =>
  props.data.map((d, i) => {
    const a = angleAt(i);
    const x = cx + labelR * Math.cos(a);
    const y = cy + labelR * Math.sin(a);
    const cos = Math.cos(a);
    const sin = Math.sin(a);
    let anchor = "middle";
    if (cos > 0.25) {
      anchor = "start";
    } else if (cos < -0.25) {
      anchor = "end";
    }
    let baseline = "middle";
    if (sin > 0.5) {
      baseline = "hanging";
    } else if (sin < -0.5) {
      baseline = "auto";
    }
    return { text: d[props.categoryKey], x, y, anchor, baseline };
  })
);
</script>
