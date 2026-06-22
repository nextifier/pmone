<template>
  <ChartContainer
    :config="config"
    class="[&_.domain]:stroke-gray-200 dark:[&_.domain]:stroke-gray-800!"
  >
    <VisXYContainer
      :data="data"
      :margin="margin"
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
      />
      <VisGroupedBar
        v-else
        :x="(d) => d[xKey]"
        :y="barY"
        :color="barColor"
        :rounded-corners="roundedCorners"
        :orientation="horizontal ? Orientation.Horizontal : Orientation.Vertical"
        :bar-padding="0.2"
      />
      <VisAxis
        v-if="horizontal"
        type="y"
        :tick-line="false"
        :domain-line="false"
        :grid-line="false"
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
    default: 6,
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

const margin = computed(() =>
  props.horizontal ? { left: 8, right: 8 } : { left: 4, right: 0, top: 8 }
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
  labelFormatter: (d) => {
    const date = new Date(d);
    return Number.isNaN(date.getTime())
      ? String(d)
      : date.toLocaleDateString("en-US", { month: "short", day: "numeric" });
  },
});
</script>
