<template>
  <ChartContainer
    :config="config"
    class="[&_.domain]:stroke-gray-200 dark:[&_.domain]:stroke-gray-800! [&_.grid_line]:[stroke-dasharray:3_3] [&_.grid_line]:stroke-border/60!"
  >
    <VisXYContainer
      :data="data"
      :svg-defs="svgDefs || undefined"
      :margin="margin"
      :padding="{ top: 12, bottom: 0 }"
      :y-domain="[0, undefined]"
    >
      <VisArea
        :x="xAccessor"
        :y="(d) => d[areaKey]"
        :color="areaFill"
        :opacity="1"
        :curve-type="resolvedCurve"
      />
      <VisLine
        :x="xAccessor"
        :y="(d) => d[lineKey]"
        :color="lineColor"
        :line-width="2.5"
        :curve-type="resolvedCurve"
      />
      <VisAxis
        type="x"
        :num-ticks="numXTicks"
        :tickTextHideOverlapping="true"
        :x="xAccessor"
        :tick-values="categoryTickValues"
        :tick-line="false"
        :domain-line="false"
        :grid-line="false"
        :tick-format="categoryTickFormat"
      />
      <VisAxis
        type="y"
        :num-ticks="5"
        :tickTextHideOverlapping="true"
        :tick-line="false"
        :domain-line="false"
        :grid-line="true"
        :tick-format="yTickFormatter || defaultYFormat"
      />
      <ChartTooltip />
      <ChartCrosshair :template="tooltipTemplate" :color="lineColor" />
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
  xKey: {
    type: String,
    default: "month",
  },
  // Key plotted as the filled area (often a pattern/zone behind the line).
  areaKey: {
    type: String,
    default: "area",
  },
  // Key plotted as the line drawn on top.
  lineKey: {
    type: String,
    default: "value",
  },
  // Fill for the area; usually a url(#pattern) reference.
  areaFill: {
    type: String,
    default: "var(--chart-1)",
  },
  // Color of the line. Falls back to the lineKey config color.
  lineColorOverride: {
    type: String,
    default: null,
  },
  svgDefs: {
    type: String,
    default: null,
  },
  curveType: {
    type: String,
    default: "natural",
  },
  legend: {
    type: Boolean,
    default: false,
  },
  numXTicks: {
    type: Number,
    default: 9,
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

const CURVE_MAP = {
  natural: CurveType.Natural,
  linear: CurveType.Linear,
  monotonex: CurveType.MonotoneX,
  basis: CurveType.Basis,
  catmullrom: CurveType.CatmullRom,
};

const resolvedCurve = computed(
  () => CURVE_MAP[String(props.curveType).toLowerCase()] || CurveType.Natural
);

const lineColor = computed(
  () => props.lineColorOverride || props.config[props.lineKey]?.color || "var(--chart-1)"
);

const categoryValues = computed(() => props.data.map((d) => d[props.xKey]));

const isCategorical = computed(() => categoryValues.value.some((v) => typeof v === "string"));

const xAccessor = computed(() =>
  isCategorical.value ? (_d, i) => i : (d) => d[props.xKey]
);

const categoryTickValues = computed(() =>
  isCategorical.value ? props.data.map((_d, i) => i) : undefined
);

const categoryTickFormat = computed(() => {
  if (isCategorical.value) {
    return (i) => {
      const label = categoryValues.value[i];
      return props.xTickFormatter ? props.xTickFormatter(label) : label;
    };
  }
  return props.xTickFormatter || defaultXFormat;
});

const defaultXFormat = (d) => {
  const date = new Date(d);
  return Number.isNaN(date.getTime()) ? String(d) : date.toLocaleDateString("en-US", { month: "short" });
};

const defaultYFormat = (d) =>
  new Intl.NumberFormat("en-US", { notation: "compact", maximumFractionDigits: 1 }).format(d);

const currentConfig = computed(() => props.config);

const tooltipTemplate = componentToString(currentConfig, ChartTooltipContent, {
  hideLabel: false,
  labelFormatter: (d) => {
    if (isCategorical.value) {
      const label = categoryValues.value[d];
      return props.xTickFormatter ? props.xTickFormatter(label) : label;
    }
    return String(d);
  },
});
</script>
