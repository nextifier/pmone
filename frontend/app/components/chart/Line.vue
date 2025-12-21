<template>
  <ChartContainer
    :config="mergedConfig"
    class="[&_.domain]:stroke-gray-200 dark:[&_.domain]:stroke-gray-800!"
  >
    <VisXYContainer
      :data="mergedData"
      :svg-defs="svgDefs"
      :margin="{ left: 8, right: 0 }"
      :padding="{ top: 12, bottom: 12 }"
      :y-domain="[0, undefined]"
    >
      <!-- Comparison Area (rendered first to be behind main area) -->
      <VisArea
        v-if="gradient && hasComparisonData"
        :x="(d) => d.date"
        :y="(d) => d[comparisonKey]"
        color="url(#fillChartComparison)"
        :opacity="0.3"
        :curve-type="CurveType.CatmullRom"
      />
      <!-- Main Area -->
      <VisArea
        v-if="gradient"
        :x="(d) => d.date"
        :y="(d) => d[dataKey]"
        color="url(#fillChart1)"
        :opacity="0.4"
        :curve-type="CurveType.CatmullRom"
      />
      <!-- Comparison Line (rendered first to be behind main line) -->
      <VisLine
        v-if="hasComparisonData"
        :x="(d) => d.date"
        :y="(d) => d[comparisonKey]"
        :color="comparisonColor"
        :line-width="1.5"
        :curve-type="CurveType.CatmullRom"
        :line-dash-array="[4, 4]"
      />
      <!-- Main Line -->
      <VisLine
        :x="(d) => d.date"
        :y="(d) => d[dataKey]"
        :color="config[dataKey]?.color || 'var(--chart-1)'"
        :line-width="1.5"
        :curve-type="CurveType.CatmullRom"
      />
      <VisAxis
        type="x"
        :num-ticks="10"
        :tickTextHideOverlapping="true"
        :x="(d) => d.date"
        :tick-line="false"
        :domain-line="false"
        :grid-line="false"
        tickTextAlign="right"
        :fullSize="false"
        :tick-format="
          (d) => {
            const date = new Date(d);
            return date.toLocaleDateString('en-US', {
              month: 'short',
              day: 'numeric',
            });
          }
        "
      />
      <VisAxis
        type="y"
        :num-ticks="5"
        :tickTextHideOverlapping="true"
        :tick-line="false"
        :domain-line="false"
        :tick-format="
          (d) => {
            return new Intl.NumberFormat('en-US', {
              notation: 'compact',
              maximumFractionDigits: 1,
            }).format(d);
          }
        "
      />
      <ChartTooltip />
      <ChartCrosshair
        :template="tooltipTemplate"
        :color="config[dataKey]?.color || 'var(--chart-1)'"
      />
    </VisXYContainer>
  </ChartContainer>
</template>

<script setup>
import { VisArea, VisAxis, VisLine, VisXYContainer } from "@unovis/vue";

import {
  ChartContainer,
  ChartCrosshair,
  ChartTooltip,
  ChartTooltipContent,
  componentToString,
} from "@/components/ui/chart";

import { CurveType } from "@unovis/ts";

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
  gradient: {
    type: Boolean,
    default: false,
  },
  comparisonData: {
    type: Array,
    default: () => [],
  },
  comparisonLabel: {
    type: String,
    default: "Previous Period",
  },
});

// Generate the comparison key name
const comparisonKey = computed(() => `${props.dataKey}_previous`);

// Check if we have comparison data
const hasComparisonData = computed(() => props.comparisonData && props.comparisonData.length > 0);

// Comparison line color
const comparisonColor = "var(--chart-3, #a1a1aa)";

// Merge current data with comparison data
const mergedData = computed(() => {
  if (!hasComparisonData.value) {
    return props.data;
  }

  // Create a map of comparison data by date
  const comparisonMap = new Map();
  props.comparisonData.forEach((item) => {
    const dateStr =
      item.date instanceof Date ? item.date.toISOString().split("T")[0] : String(item.date);
    comparisonMap.set(dateStr, item);
  });

  // Merge comparison values into current data
  return props.data.map((item) => {
    const dateStr =
      item.date instanceof Date ? item.date.toISOString().split("T")[0] : String(item.date);
    const compItem = comparisonMap.get(dateStr);

    return {
      ...item,
      [comparisonKey.value]: compItem ? compItem[props.dataKey] || 0 : null,
    };
  });
});

// Merged config including comparison series
const mergedConfig = computed(() => {
  if (!hasComparisonData.value) {
    return props.config;
  }

  return {
    ...props.config,
    [comparisonKey.value]: {
      label: props.comparisonLabel,
      color: comparisonColor,
    },
  };
});

const currentConfig = computed(() => mergedConfig.value);

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

const svgDefs = `
  <linearGradient id="fillChart1" x1="0" y1="0" x2="0" y2="1">
    <stop
      offset="5%"
      stop-color="var(--chart-1)"
      stop-opacity="0.8"
    />
    <stop
      offset="95%"
      stop-color="var(--chart-1)"
      stop-opacity="0"
    />
  </linearGradient>
  <linearGradient id="fillChart2" x1="0" y1="0" x2="0" y2="1">
    <stop
      offset="5%"
      stop-color="var(--chart-2)"
      stop-opacity="0.8"
    />
    <stop
      offset="95%"
      stop-color="var(--chart-2)"
      stop-opacity="0"
    />
  </linearGradient>
  <linearGradient id="fillChartComparison" x1="0" y1="0" x2="0" y2="1">
    <stop
      offset="5%"
      stop-color="var(--chart-3, #a1a1aa)"
      stop-opacity="0.4"
    />
    <stop
      offset="95%"
      stop-color="var(--chart-3, #a1a1aa)"
      stop-opacity="0"
    />
  </linearGradient>
`;
</script>
