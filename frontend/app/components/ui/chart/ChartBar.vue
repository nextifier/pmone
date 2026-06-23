<template>
  <ChartContainer
    :config="config"
    class="[&_.domain]:stroke-gray-200 dark:[&_.domain]:stroke-gray-800!"
  >
    <VisXYContainer
      :data="data"
      :margin="resolvedMargin"
      :y-domain="horizontal ? undefined : [0, undefined]"
      :x-domain="horizontal ? [0, undefined] : undefined"
    >
      <VisStackedBar
        v-if="stacked"
        :x="(d) => d[xKey]"
        :y="barY"
        :color="barColor"
        :rounded-corners="roundedCorners"
        :orientation="horizontal ? Orientation.Horizontal : Orientation.Vertical"
        :bar-padding="0.2"
        :bar-max-width="barMaxWidth || undefined"
      />
      <VisGroupedBar
        v-else
        :x="(d) => d[xKey]"
        :y="barY"
        :color="barColor"
        :rounded-corners="roundedCorners"
        :orientation="horizontal ? Orientation.Horizontal : Orientation.Vertical"
        :bar-padding="0.2"
        :bar-max-width="barMaxWidth || undefined"
      />
      <VisAxis
        v-if="horizontal"
        type="y"
        :tick-line="false"
        :domain-line="false"
        :grid-line="false"
        :tickTextHideOverlapping="true"
        :num-ticks="categoryValues.length"
        :tick-values="categoryValues"
        :tick-format="xTickFormatter || defaultXFormat"
      />
      <template v-else>
        <VisAxis
          type="x"
          :x="(d) => d[xKey]"
          :tick-line="false"
          :domain-line="false"
          :grid-line="false"
          :tickTextHideOverlapping="true"
          :num-ticks="categoryValues.length"
          :tick-values="categoryValues"
          :tick-format="xTickFormatter || defaultXFormat"
        />
        <VisAxis
          type="y"
          :num-ticks="3"
          :tick-line="false"
          :domain-line="false"
          :tick-format="yTickFormatter || defaultYFormat"
        />
      </template>
      <ChartTooltip />
      <ChartCrosshair :template="tooltipTemplate" color="#0000" />
    </VisXYContainer>
    <ChartLegendContent v-if="legend" />
  </ChartContainer>
</template>

<script setup>
import { VisAxis, VisGroupedBar, VisStackedBar, VisXYContainer } from "@unovis/vue";
import { Orientation } from "@unovis/ts";
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
  horizontal: {
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
  roundedCorners: {
    type: Number,
    default: 4,
  },
  // Caps bar thickness so sparse data (few categories) doesn't blow up into
  // giant bars. null leaves Unovis' default (bars fill their band).
  barMaxWidth: {
    type: Number,
    default: null,
  },
  margin: {
    type: Object,
    default: null,
  },
  xTickFormatter: {
    type: Function,
    default: null,
  },
  yTickFormatter: {
    type: Function,
    default: null,
  },
  // Formats the value shown in the tooltip (e.g. currency). Falls back to
  // toLocaleString when not provided.
  valueFormatter: {
    type: Function,
    default: null,
  },
});

const keys = computed(() =>
  props.dataKeys && props.dataKeys.length ? props.dataKeys : [props.dataKey]
);

const isMulti = computed(() => keys.value.length > 1);

const barY = computed(() => {
  const accessors = keys.value.map((key) => (d) => d[key]);
  return accessors.length === 1 ? accessors[0] : accessors;
});

const barColor = computed(() => {
  const colors = keys.value.map((key) => props.config[key]?.color || "var(--chart-1)");
  return colors.length === 1 ? colors[0] : colors;
});

const categoryValues = computed(() => props.data.map((d) => d[props.xKey]));

const resolvedMargin = computed(
  () =>
    props.margin ||
    (props.horizontal
      ? { top: 4, right: 8, bottom: 4, left: 60 }
      : { top: 6, right: 8, bottom: 18, left: 26 })
);

const defaultXFormat = (d) => {
  const date = new Date(d);
  return Number.isNaN(date.getTime()) ? String(d) : date.toLocaleDateString("en-US", { month: "short" });
};

const defaultYFormat = (d) =>
  new Intl.NumberFormat("en-US", { notation: "compact", maximumFractionDigits: 1 }).format(d);

const currentConfig = computed(() => props.config);

const tooltipTemplate = componentToString(currentConfig, ChartTooltipContent, {
  indicator: isMulti.value ? "dashed" : "dot",
  hideLabel: false,
  valueFormatter: props.valueFormatter || undefined,
  // Reuse the axis formatter for the tooltip's x label so index-based charts
  // (numeric x) read the real category instead of a bogus epoch date.
  labelFormatter: (d) => {
    if (props.xTickFormatter) {
      return props.xTickFormatter(d);
    }
    const date = new Date(d);
    return Number.isNaN(date.getTime())
      ? String(d)
      : date.toLocaleDateString("en-US", { month: "short", day: "numeric" });
  },
});
</script>
