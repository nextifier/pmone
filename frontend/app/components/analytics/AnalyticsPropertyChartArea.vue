<script setup lang="ts">
import type { ChartConfig } from "@/components/ui/chart";
import { VisArea, VisAxis, VisLine, VisXYContainer } from "@unovis/vue";
import {
  ChartContainer,
  ChartCrosshair,
  ChartTooltip,
  ChartTooltipContent,
  componentToString,
} from "@/components/ui/chart";

const props = defineProps<{
  rows: Array<{
    date: string;
    activeUsers: number;
    screenPageViews: number;
    sessions: number;
  }>;
  propertyName: string;
}>();

const { $dayjs } = useNuxtApp();

// Transform data for chart
const chartData = computed(() => {
  if (!props.rows || props.rows.length === 0) return [];

  return props.rows
    .map((row, index) => {
      // Parse date from format YYYYMMDD
      const year = row.date.substring(0, 4);
      const month = row.date.substring(4, 6);
      const day = row.date.substring(6, 8);
      const date = $dayjs(`${year}-${month}-${day}`);

      return {
        index: index + 1,
        date: row.date,
        dateLabel: date.format("MMM DD"),
        fullDate: date.format("MMM DD, YYYY"),
        activeVisitors: row.activeUsers || 0,
      };
    })
    .sort((a, b) => a.date.localeCompare(b.date));
});

type Data = typeof chartData.value[number];

const chartConfig = {
  activeVisitors: {
    label: "Active Visitors",
    color: "hsl(var(--chart-1))",
  },
} satisfies ChartConfig;

const svgDefs = `
  <linearGradient id="fillActiveVisitors-${props.propertyName}" x1="0" y1="0" x2="0" y2="1">
    <stop offset="5%" stop-color="var(--color-activeVisitors)" stop-opacity="0.8" />
    <stop offset="95%" stop-color="var(--color-activeVisitors)" stop-opacity="0.1" />
  </linearGradient>
`;
</script>

<template>
  <div v-if="chartData.length > 0" class="overflow-hidden">
    <ChartContainer :config="chartConfig" class="h-[180px] w-full">
      <VisXYContainer :data="chartData" :svg-defs="svgDefs">
        <VisArea
          :x="(d: Data) => d.index"
          :y="(d: Data) => d.activeVisitors"
          :color="`url(#fillActiveVisitors-${propertyName})`"
          :opacity="0.4"
        />
        <VisLine
          :x="(d: Data) => d.index"
          :y="(d: Data) => d.activeVisitors"
          :color="chartConfig.activeVisitors.color"
          :line-width="2"
        />
        <VisAxis
          type="x"
          :x="(d: Data) => d.index"
          :tick-line="false"
          :domain-line="false"
          :grid-line="false"
          :num-ticks="Math.min(chartData.length, 6)"
          :tick-format="(d: number, index: number) => chartData[index]?.dateLabel || ''"
        />
        <VisAxis
          type="y"
          :num-ticks="3"
          :tick-line="false"
          :domain-line="false"
          :tick-format="(d: number) => ''"
        />
        <ChartTooltip />
        <ChartCrosshair
          :template="componentToString(chartConfig, ChartTooltipContent, { labelKey: 'fullDate' })"
          :color="chartConfig.activeVisitors.color"
        />
      </VisXYContainer>
    </ChartContainer>
  </div>
</template>
