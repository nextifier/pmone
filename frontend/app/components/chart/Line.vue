<template>
  <ChartContainer
    :config="config"
    class="[&_.domain]:stroke-gray-200 dark:[&_.domain]:stroke-gray-800!"
  >
    <VisXYContainer
      :data="data"
      :svg-defs="svgDefs"
      :margin="{ left: 8, right: 0 }"
      :padding="{ top: 12, bottom: 12 }"
      :y-domain="[0, undefined]"
    >
      <VisArea
        v-if="gradient"
        :x="(d) => d.date"
        :y="(d) => d[dataKey]"
        :color="(d, i) => ['url(#fillChart1)', 'url(#fillChart2)'][i]"
        :opacity="0.4"
        :curve-type="CurveType.CatmullRom"
      />
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
