<template>
  <ChartContainer
    :config="config"
    class="[&_.domain]:stroke-gray-200 dark:[&_.domain]:stroke-gray-800!"
  >
    <VisXYContainer
      :data="plotData"
      :svg-defs="gradient ? svgDefs : undefined"
      :margin="margin"
      :padding="{ top: 12, bottom: 0 }"
      :y-domain="[0, undefined]"
    >
      <VisArea
        :x="(d) => d[xKey]"
        :y="areaY"
        :color="areaColor"
        :opacity="areaOpacity"
        :curve-type="CurveType.Natural"
      />
      <VisLine
        :x="(d) => d[xKey]"
        :y="areaY"
        :color="lineColor"
        :line-width="1.5"
        :curve-type="CurveType.Natural"
      />
      <VisAxis
        type="x"
        :num-ticks="numXTicks"
        :tickTextHideOverlapping="true"
        :x="(d) => d[xKey]"
        :tick-line="false"
        :domain-line="false"
        :grid-line="false"
        tickTextAlign="right"
        :fullSize="false"
        :tick-format="xTickFormatter || defaultXFormat"
      />
      <VisAxis
        type="y"
        :num-ticks="5"
        :tickTextHideOverlapping="true"
        :tick-line="false"
        :domain-line="false"
        :tick-format="yTickFormatter || defaultYFormat"
      />
      <ChartTooltip />
      <ChartCrosshair :template="tooltipTemplate" :color="crosshairColor" />
    </VisXYContainer>
    <ChartLegendContent v-if="legend" />
  </ChartContainer>
</template>

<script setup>
import { VisArea, VisAxis, VisLine, VisXYContainer } from "@unovis/vue";
import { CurveType } from "@unovis/ts";
import {
  ChartContainer,
  ChartCrosshair,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
  componentToString,
} from ".";

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
  dataKeys: {
    type: Array,
    default: null,
  },
  xKey: {
    type: String,
    default: "date",
  },
  gradient: {
    type: Boolean,
    default: false,
  },
  stacked: {
    type: Boolean,
    default: false,
  },
  legend: {
    type: Boolean,
    default: false,
  },
  numXTicks: {
    type: Number,
    default: 6,
  },
  margin: {
    type: Object,
    default: () => ({ top: 8, right: 8, bottom: 18, left: 26 }),
  },
  xTickFormatter: {
    type: Function,
    default: null,
  },
  yTickFormatter: {
    type: Function,
    default: null,
  },
});

const keys = computed(() =>
  props.dataKeys && props.dataKeys.length ? props.dataKeys : [props.dataKey]
);

const isStacked = computed(() => props.stacked && keys.value.length > 1);

// When stacking, precompute cumulative totals per row so each band sits on top
// of the previous one. Original keys stay on the row so the tooltip still shows
// the real per-series values.
const plotData = computed(() => {
  if (!isStacked.value) {
    return props.data;
  }

  return props.data.map((row) => {
    const out = { ...row };
    let acc = 0;
    for (const key of keys.value) {
      acc += Number(row[key]) || 0;
      out[`__stack_${key}`] = acc;
    }
    return out;
  });
});

// Draw stacked bands back-to-front so the lowest band paints on top of its slice.
const drawKeys = computed(() => (isStacked.value ? [...keys.value].reverse() : keys.value));

const areaY = computed(() => {
  const accessors = drawKeys.value.map((key) =>
    isStacked.value ? (d) => d[`__stack_${key}`] : (d) => d[key]
  );
  return accessors.length === 1 ? accessors[0] : accessors;
});

const colorList = computed(() =>
  drawKeys.value.map((key) => props.config[key]?.color || "var(--chart-1)")
);

const areaColor = computed(() => {
  const colors = drawKeys.value.map((key) =>
    props.gradient ? `url(#fill-${key})` : props.config[key]?.color || "var(--chart-1)"
  );
  return colors.length === 1 ? colors[0] : colors;
});

const lineColor = computed(() => (colorList.value.length === 1 ? colorList.value[0] : colorList.value));

const crosshairColor = (d, i) => colorList.value[i % colorList.value.length];

const areaOpacity = computed(() => (isStacked.value ? 0.7 : props.gradient ? 0.4 : 0.35));

const svgDefs = computed(() =>
  keys.value
    .map((key) => {
      const color = props.config[key]?.color || "var(--chart-1)";
      return `<linearGradient id="fill-${key}" x1="0" y1="0" x2="0" y2="1"><stop offset="5%" stop-color="${color}" stop-opacity="0.8" /><stop offset="95%" stop-color="${color}" stop-opacity="0.1" /></linearGradient>`;
    })
    .join("")
);

const defaultXFormat = (d) => {
  const date = new Date(d);
  return date.toLocaleDateString("en-US", { month: "short", day: "numeric" });
};

const defaultYFormat = (d) =>
  new Intl.NumberFormat("en-US", { notation: "compact", maximumFractionDigits: 1 }).format(d);

const currentConfig = computed(() => props.config);

const tooltipTemplate = componentToString(currentConfig, ChartTooltipContent, {
  hideLabel: false,
  labelFormatter: (d) => {
    const date = new Date(d);
    return date.toLocaleDateString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
    });
  },
});
</script>
