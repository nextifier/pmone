<template>
  <div
    class="overflow-hidden px-0! [&_svg>g]:origin-center! [&_svg>g]:not-first:scale-x-110 [&_svg>g]:first:scale-x-90!"
  >
    <ChartContainer :config="config">
      <VisXYContainer :data="data" :svg-defs="svgDefs">
        <VisArea
          :x="(d) => d.month"
          :y="[(d) => d.mobile, (d) => d.desktop]"
          :color="(d, i) => ['url(#fillMobile)', 'url(#fillDesktop)'][i]"
          :opacity="0.4"
        />
        <VisLine
          :x="(d) => d.month"
          :y="[(d) => d.mobile, (d) => d.mobile + d.desktop]"
          :color="(d, i) => [config.mobile.color, config.desktop.color][i]"
          :line-width="1"
        />
        <VisAxis
          type="x"
          :x="(d) => d.month"
          :tick-line="false"
          :domain-line="false"
          :grid-line="false"
          :num-ticks="6"
          :tick-format="(d, index) => data[index]"
        />
        <VisAxis
          type="y"
          :num-ticks="3"
          :tick-line="false"
          :domain-line="false"
          :tick-format="(d) => ''"
        />
        <ChartTooltip />
        <ChartCrosshair
          :template="componentToString(config, ChartTooltipContent, { labelKey: 'monthLabel' })"
          :color="(d, i) => [config.mobile.color, config.desktop.color][i % 2]"
        />
      </VisXYContainer>
    </ChartContainer>
  </div>
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
});

const svgDefs = `
  <linearGradient id="fillDesktop" x1="0" y1="0" x2="0" y2="1">
    <stop offset="5%" stop-color="var(--color-desktop)" stop-opacity="0.8" />
    <stop offset="95%" stop-color="var(--color-desktop)" stop-opacity="0.1" />
  </linearGradient>
  <linearGradient id="fillMobile" x1="0" y1="0" x2="0" y2="1">
    <stop offset="5%" stop-color="var(--color-mobile)" stop-opacity="0.8" />
    <stop offset="95%" stop-color="var(--color-mobile)" stop-opacity="0.1" />
  </linearGradient>
`;
</script>
