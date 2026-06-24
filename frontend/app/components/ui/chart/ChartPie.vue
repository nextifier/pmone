<template>
  <ChartContainer :config="config" :class="containerClass" :style="mergedStyle">
    <template v-if="resolvedLayers">
      <VisSingleContainer
        v-for="(layer, idx) in resolvedLayers"
        :key="idx"
        :data="layer.data"
        :margin="{ top: 20, bottom: 20 }"
      >
        <VisDonut
          :value="(d) => d[layer.valueKey]"
          :color="segmentColorAccessor"
          :arc-width="layer.arcWidth"
          :radius="layer.radius || undefined"
        />
        <ChartTooltip :triggers="tooltipTriggers" />
      </VisSingleContainer>
    </template>
    <!-- Active segment: base donut + a larger overlay arc, both in a sized,
         relative box so the legend can sit below without overlapping. Unovis sets
         an inline position, so absolute/inset are forced with !. -->
    <div
      v-else-if="activeArcWidth"
      class="relative mx-auto aspect-square w-full max-w-[250px] [&_[data-vis-single-container]]:absolute! [&_[data-vis-single-container]]:inset-0!"
    >
      <VisSingleContainer
        :data="data"
        :svg-defs="svgDefs || undefined"
        :margin="{ top: 20, bottom: 20 }"
      >
        <VisDonut
          :value="(d) => d[valueKey]"
          :color="segmentColorAccessor"
          :arc-width="arcWidth"
          :corner-radius="cornerRadius"
          :pad-angle="padAngle"
          :radius="radius || undefined"
          :central-label="resolvedCenterLabel || ''"
          :central-sub-label="centerSubLabel || ''"
          :central-label-offset-y="centerSubLabel ? 10 : 0"
        />
        <ChartTooltip :triggers="tooltipTriggers" />
      </VisSingleContainer>
      <VisSingleContainer
        :data="data"
        :margin="{ top: 20, bottom: 20 }"
        class="pointer-events-none"
      >
        <VisDonut
          :value="(d) => d[valueKey]"
          :color="activeColorAccessor"
          :arc-width="activeArcWidth"
          :corner-radius="cornerRadius"
          :pad-angle="padAngle"
          :radius="activeOuterRadius"
        />
      </VisSingleContainer>
    </div>
    <template v-else>
      <VisSingleContainer
        :data="data"
        :svg-defs="svgDefs || undefined"
        :margin="{ top: 20, bottom: 20 }"
      >
        <VisDonut
          :value="(d) => d[valueKey]"
          :color="segmentColorAccessor"
          :arc-width="arcWidth"
          :corner-radius="cornerRadius"
          :pad-angle="padAngle"
          :radius="radius || undefined"
          :central-label="resolvedCenterLabel || ''"
          :central-sub-label="centerSubLabel || ''"
          :central-label-offset-y="centerSubLabel ? 10 : 0"
        />
        <ChartTooltip :triggers="tooltipTriggers" />
      </VisSingleContainer>
    </template>
    <ChartLegendContent v-if="legend" :name-key="nameKey" />
  </ChartContainer>
</template>

<script setup>
import { Donut } from "@unovis/ts";
import { VisDonut, VisSingleContainer } from "@unovis/vue";
import {
  ChartContainer,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
  componentToString,
} from ".";

const props = defineProps({
  data: {
    type: Array,
    default: () => [],
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
  // 0 renders a full pie; any positive value renders a donut with that ring width.
  arcWidth: {
    type: Number,
    default: 0,
  },
  // Explicit outer radius (px). Leave null for Unovis' auto sizing.
  radius: {
    type: Number,
    default: null,
  },
  // Rounded segment ends.
  cornerRadius: {
    type: Number,
    default: 0,
  },
  // Gap between segments, in radians.
  padAngle: {
    type: Number,
    default: 0,
  },
  centerLabel: {
    type: String,
    default: null,
  },
  centerSubLabel: {
    type: String,
    default: null,
  },
  total: {
    type: [Number, String],
    default: null,
  },
  legend: {
    type: Boolean,
    default: false,
  },
  // Concentric rings for a stacked/nested pie. Each entry:
  // { data, valueKey, arcWidth, radius }.
  layers: {
    type: Array,
    default: null,
  },
  // Raw SVG <defs> string injected into the chart (gradients/patterns/filters).
  svgDefs: {
    type: String,
    default: null,
  },
  // Override the per-segment fill. Receives the row; should return a color or
  // url(#id). Defaults to the row's own `fill`, then the config color.
  segmentFill: {
    type: Function,
    default: null,
  },
  // Stroke drawn between segments (the gap ring). ReUI uses var(--background).
  segmentStrokeColor: {
    type: String,
    default: null,
  },
  segmentStrokeWidth: {
    type: Number,
    default: 4,
  },
  // SVG filter url applied to the donut group (e.g. a drop shadow).
  donutFilter: {
    type: String,
    default: null,
  },
  // Index of the segment to render expanded.
  activeIndex: {
    type: Number,
    default: null,
  },
  // Outer radius (px) of the expanded active segment.
  activeOuterRadius: {
    type: Number,
    default: 110,
  },
});

const segmentColorAccessor = computed(() => {
  if (props.segmentFill) {
    return props.segmentFill;
  }
  return (d) => d.fill || props.config[d[props.nameKey]]?.color || "var(--chart-1)";
});

const totalSum = computed(() =>
  props.data.reduce((acc, d) => acc + (Number(d[props.valueKey]) || 0), 0)
);

const resolvedCenterLabel = computed(() => {
  if (props.centerLabel) {
    return props.centerLabel;
  }
  if (props.total !== null) {
    return typeof props.total === "number" ? props.total.toLocaleString() : props.total;
  }
  if (props.centerSubLabel) {
    return totalSum.value.toLocaleString();
  }
  return null;
});

const resolvedLayers = computed(() =>
  props.layers && props.layers.length
    ? props.layers.map((layer) => ({
        arcWidth: 25,
        radius: null,
        valueKey: props.valueKey,
        ...layer,
      }))
    : null
);

// Active segment expansion: the overlay donut renders the SAME data with the
// SAME padAngle so d3 computes identical segment angles to the base donut, then
// only the active segment is painted (others transparent) at a larger radius.
// This guarantees pixel-perfect alignment with the base segment (no slivers,
// consistent gaps) instead of re-deriving angles manually.
const validActiveIndex = computed(
  () =>
    props.activeIndex !== null &&
    props.activeIndex >= 0 &&
    props.activeIndex < props.data.length
);

const activeArcWidth = computed(() => {
  if (!validActiveIndex.value) {
    return null;
  }
  const baseInner = (props.radius || props.activeOuterRadius - 15) - props.arcWidth;
  return props.activeOuterRadius - baseInner;
});

const activeColorAccessor = computed(() => {
  const base = segmentColorAccessor.value;
  return (d, i) => (i === props.activeIndex ? base(d) : "transparent");
});

const baseClass = computed(() => {
  const base = "mx-auto aspect-square max-h-[250px] h-auto!";
  if (resolvedLayers.value) {
    return `relative ${base} [&_[data-vis-single-container]]:absolute!`;
  }
  if (validActiveIndex.value) {
    // Auto-height column: the inner wrapper handles the square donut box and the
    // legend flows beneath it.
    return "mx-auto flex w-full max-w-[280px] flex-col items-center h-auto!";
  }
  return base;
});

const containerClass = computed(() => {
  const classes = [baseClass.value];
  if (props.donutFilter) {
    classes.push(`[&_.vis-donut]:[filter:${props.donutFilter}]`);
  }
  return classes.join(" ");
});

const mergedStyle = computed(() => {
  // Unovis draws a background ring (default near-white #E7E9F3) behind the
  // segments; padAngle gaps would expose it. ReUI has no such ring, so make it
  // transparent and let the gaps fall through to the card background.
  const style = { "--vis-donut-background-color": "transparent" };
  if (resolvedCenterLabel.value) {
    style["--vis-donut-central-label-font-size"] = "1.5rem";
    style["--vis-donut-central-label-font-weight"] = "600";
    style["--vis-donut-central-sub-label-text-color"] = "var(--muted-foreground)";
  }
  if (props.segmentStrokeColor) {
    style["--vis-donut-segment-stroke-color"] = props.segmentStrokeColor;
    style["--vis-donut-segment-stroke-width"] = `${props.segmentStrokeWidth}px`;
  }
  return Object.keys(style).length ? style : undefined;
});

const currentConfig = computed(() => props.config);

const tooltipTemplate = componentToString(currentConfig, ChartTooltipContent, {
  hideLabel: true,
  nameKey: props.nameKey,
});

const tooltipTriggers = {
  [Donut.selectors.segment]: tooltipTemplate,
};
</script>
