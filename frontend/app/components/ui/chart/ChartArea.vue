<template>
  <ChartContainer
    :config="config"
    :class="containerClass"
  >
    <VisXYContainer
      :data="plotData"
      :svg-defs="resolvedSvgDefs"
      :margin="margin"
      :padding="{ top: 12, bottom: 0 }"
      :y-domain="[0, undefined]"
    >
      <VisArea
        :x="xAccessor"
        :y="areaY"
        :color="areaColor"
        :opacity="areaOpacity"
        :curve-type="resolvedCurve"
      />
      <VisLine
        :x="xAccessor"
        :y="areaY"
        :color="lineColor"
        :line-width="lineWidthAccessor"
        :line-dash-array="lineDashAccessor"
        :curve-type="resolvedCurve"
      />
      <VisScatter
        v-if="dots"
        :x="xAccessor"
        :y="areaY"
        :color="lineColor"
        :size="dotSize"
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
        tickTextAlign="right"
        :fullSize="false"
        :tick-format="categoryTickFormat"
      />
      <VisAxis
        v-if="!hideYAxis || grid"
        type="y"
        :num-ticks="5"
        :tickTextHideOverlapping="true"
        :tick-line="false"
        :domain-line="false"
        :grid-line="grid"
        :tick-format="hideYAxis ? blankFormat : yTickFormatter || defaultYFormat"
      />
      <ChartTooltip />
      <ChartCrosshair :template="tooltipTemplate" :color="crosshairColor" />
    </VisXYContainer>
    <ChartLegendContent v-if="legend" />
  </ChartContainer>
</template>

<script setup>
import { VisArea, VisAxis, VisLine, VisScatter, VisXYContainer } from "@unovis/vue";
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
  // Curve interpolation: natural | linear | step | stepAfter | stepBefore | monotoneX | basis | catmullRom.
  curveType: {
    type: String,
    default: "natural",
  },
  // Raw SVG <defs> string injected into the chart. Overrides the auto gradient
  // defs when set; reference patterns/gradients from areaFill via url(#id).
  svgDefs: {
    type: String,
    default: null,
  },
  // Override the area fill. String applies to every series; an object maps each
  // series key to its own fill (color or url(#pattern)).
  areaFill: {
    type: [String, Object],
    default: null,
  },
  // Per-series fill opacity. Defaults adapt to gradient/stacked modes.
  fillOpacity: {
    type: Number,
    default: null,
  },
  // Draw horizontal dashed grid lines (mirrors a CartesianGrid).
  grid: {
    type: Boolean,
    default: false,
  },
  // Series keys whose line is rendered dashed.
  dashedKeys: {
    type: Array,
    default: () => [],
  },
  // Map of series key → line width. Falls back to 1.5.
  strokeWidthByKey: {
    type: Object,
    default: null,
  },
  // SVG filter url applied to the line/area (e.g. a glow filter).
  lineFilter: {
    type: String,
    default: null,
  },
  // Render point markers at each datum.
  dots: {
    type: Boolean,
    default: false,
  },
  // Diameter of the dot markers.
  dotSize: {
    type: Number,
    default: 8,
  },
  // SVG filter url applied to the dots.
  dotFilter: {
    type: String,
    default: null,
  },
  // Hide the Y axis (ReUI area cards omit it).
  hideYAxis: {
    type: Boolean,
    default: false,
  },
});

const CURVE_MAP = {
  natural: CurveType.Natural,
  linear: CurveType.Linear,
  step: CurveType.Step,
  stepafter: CurveType.StepAfter,
  stepbefore: CurveType.StepBefore,
  monotonex: CurveType.MonotoneX,
  basis: CurveType.Basis,
  catmullrom: CurveType.CatmullRom,
};

const resolvedCurve = computed(
  () => CURVE_MAP[String(props.curveType).toLowerCase()] || CurveType.Natural
);

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

function fillFor(key) {
  if (props.areaFill) {
    if (typeof props.areaFill === "string") {
      return props.areaFill;
    }
    if (props.areaFill[key]) {
      return props.areaFill[key];
    }
  }
  if (props.gradient) {
    return `url(#fill-${key})`;
  }
  return props.config[key]?.color || "var(--chart-1)";
}

const areaColor = computed(() => {
  const colors = drawKeys.value.map(fillFor);
  return colors.length === 1 ? colors[0] : colors;
});

const lineColor = computed(() => (colorList.value.length === 1 ? colorList.value[0] : colorList.value));

const lineWidthAccessor = computed(() => {
  if (!props.strokeWidthByKey) {
    return 1.5;
  }
  const widths = drawKeys.value.map((key) => props.strokeWidthByKey[key] ?? 1.5);
  return (_d, i) => widths[i % widths.length];
});

const lineDashAccessor = computed(() => {
  if (!props.dashedKeys.length) {
    return undefined;
  }
  const dashes = drawKeys.value.map((key) => (props.dashedKeys.includes(key) ? [3, 3] : []));
  return (_d, i) => dashes[i % dashes.length];
});

const crosshairColor = (d, i) => colorList.value[i % colorList.value.length];

const areaOpacity = computed(() => {
  if (props.fillOpacity !== null) {
    return props.fillOpacity;
  }
  return isStacked.value ? 0.7 : props.gradient ? 0.4 : 0.35;
});

// String categories (e.g. "January") aren't numeric, so map them to row indices
// and restore the label on the axis/tooltip.
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

const autoGradientDefs = computed(() =>
  keys.value
    .map((key) => {
      const color = props.config[key]?.color || "var(--chart-1)";
      return `<linearGradient id="fill-${key}" x1="0" y1="0" x2="0" y2="1"><stop offset="5%" stop-color="${color}" stop-opacity="0.8" /><stop offset="95%" stop-color="${color}" stop-opacity="0.1" /></linearGradient>`;
    })
    .join("")
);

const resolvedSvgDefs = computed(() => {
  if (props.svgDefs) {
    return props.svgDefs;
  }
  return props.gradient ? autoGradientDefs.value : undefined;
});

const containerClass = computed(() => {
  const classes = ["[&_.domain]:stroke-gray-200 dark:[&_.domain]:stroke-gray-800!"];
  if (props.grid) {
    classes.push("[&_.grid_line]:[stroke-dasharray:3_3] [&_.grid_line]:stroke-border/60!");
  }
  if (props.lineFilter) {
    classes.push(`[&_.line]:[filter:${props.lineFilter}] [&_.area]:[filter:${props.lineFilter}]`);
  }
  if (props.dotFilter) {
    classes.push(`[&_.vis-scatter]:[filter:${props.dotFilter}]`);
  }
  return classes.join(" ");
});

const defaultXFormat = (d) => {
  const date = new Date(d);
  return date.toLocaleDateString("en-US", { month: "short", day: "numeric" });
};

const defaultYFormat = (d) =>
  new Intl.NumberFormat("en-US", { notation: "compact", maximumFractionDigits: 1 }).format(d);

// Keeps grid lines while hiding the y-axis labels (matches ReUI's grid-only look).
const blankFormat = () => "";

const currentConfig = computed(() => props.config);

const tooltipTemplate = componentToString(currentConfig, ChartTooltipContent, {
  hideLabel: false,
  labelFormatter: (d) => {
    if (isCategorical.value) {
      const label = categoryValues.value[d];
      return props.xTickFormatter ? props.xTickFormatter(label) : label;
    }
    const date = new Date(d);
    return date.toLocaleDateString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
    });
  },
});
</script>
