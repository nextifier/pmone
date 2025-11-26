<template>
  <ChartContainer :config="config">
    <VisXYContainer :data="data" :svg-defs="svgDefs">
      <VisArea
        :x="(d) => d.date"
        :y="(d) => d[dataKey]"
        :color="(d, i) => ['url(#fillChart1)', 'url(#fillChart2)'][i]"
        :opacity="0.4"
      />
      <VisLine
        :x="(d) => d.date"
        :y="(d) => d[dataKey]"
        :color="config[dataKey]?.color || 'var(--chart-1)'"
        :line-width="1"
      />
      <VisAxis
        type="x"
        :num-ticks="10"
        :tickTextHideOverlapping="true"
        :x="(d) => d.date"
        :tick-line="false"
        :domain-line="false"
        :grid-line="false"
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
});

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
`;
</script>
