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
        pageviews: row.screenPageViews || 0,
        users: row.activeUsers || 0,
        sessions: row.sessions || 0,
      };
    })
    .sort((a, b) => a.date.localeCompare(b.date));
});

type Data = typeof chartData.value[number];

const chartConfig = {
  pageviews: {
    label: "Page Views",
    color: "var(--chart-1)",
  },
  users: {
    label: "Active Users",
    color: "var(--chart-2)",
  },
} satisfies ChartConfig;

const svgDefs = `
  <linearGradient id="fillPageviews-${props.propertyName}" x1="0" y1="0" x2="0" y2="1">
    <stop offset="5%" stop-color="var(--color-pageviews)" stop-opacity="0.8" />
    <stop offset="95%" stop-color="var(--color-pageviews)" stop-opacity="0.1" />
  </linearGradient>
  <linearGradient id="fillUsers-${props.propertyName}" x1="0" y1="0" x2="0" y2="1">
    <stop offset="5%" stop-color="var(--color-users)" stop-opacity="0.8" />
    <stop offset="95%" stop-color="var(--color-users)" stop-opacity="0.1" />
  </linearGradient>
`;
</script>

<template>
  <div v-if="chartData.length > 0" class="border-border bg-card rounded-lg border">
    <div class="border-border border-b px-4 py-3">
      <h3 class="text-foreground flex items-center gap-2 text-sm font-semibold">
        <Icon name="hugeicons:chart-line-data-03" class="size-4" />
        Daily Performance
      </h3>
      <p class="text-muted-foreground text-xs">Page views and users over time</p>
    </div>

    <div class="p-4">
      <ChartContainer :config="chartConfig" class="min-h-[200px] w-full">
        <VisXYContainer :data="chartData" :svg-defs="svgDefs">
          <VisArea
            :x="(d: Data) => d.index"
            :y="[(d: Data) => d.users, (d: Data) => d.pageviews]"
            :color="(d: Data, i: number) => [`url(#fillUsers-${propertyName})`, `url(#fillPageviews-${propertyName})`][i]"
            :opacity="0.4"
          />
          <VisLine
            :x="(d: Data) => d.index"
            :y="[(d: Data) => d.users, (d: Data) => d.pageviews]"
            :color="(d: Data, i: number) => [chartConfig.users.color, chartConfig.pageviews.color][i]"
            :line-width="2"
          />
          <VisAxis
            type="x"
            :x="(d: Data) => d.index"
            :tick-line="false"
            :domain-line="false"
            :grid-line="false"
            :num-ticks="Math.min(chartData.length, 7)"
            :tick-format="(d: number, index: number) => chartData[index]?.dateLabel || ''"
          />
          <VisAxis type="y" :num-ticks="4" :tick-line="false" :domain-line="false" />
          <ChartTooltip />
          <ChartCrosshair
            :template="componentToString(chartConfig, ChartTooltipContent, { labelKey: 'fullDate' })"
            :color="(d: Data, i: number) => [chartConfig.users.color, chartConfig.pageviews.color][i % 2]"
          />
        </VisXYContainer>
      </ChartContainer>
    </div>

    <div class="border-border flex items-center gap-4 border-t px-4 py-2 text-xs">
      <div class="flex items-center gap-2">
        <div class="size-3 rounded" :style="{ backgroundColor: chartConfig.pageviews.color }"></div>
        <span class="text-muted-foreground">Page Views</span>
      </div>
      <div class="flex items-center gap-2">
        <div class="size-3 rounded" :style="{ backgroundColor: chartConfig.users.color }"></div>
        <span class="text-muted-foreground">Active Users</span>
      </div>
    </div>
  </div>
</template>
