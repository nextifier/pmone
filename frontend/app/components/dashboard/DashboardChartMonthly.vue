<template>
  <Card>
    <CardHeader>
      <CardTitle>{{ title }}</CardTitle>
      <CardDescription>
        <template v-if="loading">Loading…</template>
        <template v-else>{{ formatNumber(total) }} {{ description }}</template>
      </CardDescription>
    </CardHeader>
    <CardContent>
      <Skeleton v-if="loading" class="h-[250px] w-full" />
      <div
        v-else-if="!hasData"
        class="flex h-[250px] w-full items-center justify-center text-sm text-muted-foreground"
      >
        {{ emptyText }}
      </div>
      <ChartContainer v-else :config="chartConfig" class="h-[250px] w-full" cursor>
        <VisXYContainer :data="chartData" :margin="{ left: -24 }" :y-domain="[0, undefined]">
          <VisGroupedBar
            v-if="variant === 'bar'"
            :x="(d) => d.timestamp"
            :y="accessor"
            :color="color"
            :rounded-corners="6"
            :bar-padding="0.2"
          />
          <VisLine
            v-else
            :x="(d) => d.timestamp"
            :y="accessor"
            :color="color"
            :line-width="2"
            :curve-type="CurveType.CatmullRom"
          />
          <VisAxis
            type="x"
            :x="(d) => d.timestamp"
            :tick-line="false"
            :domain-line="false"
            :grid-line="false"
            :num-ticks="6"
            :tick-values="chartData.map((d) => d.timestamp)"
            :tick-format="formatMonthLabel"
          />
          <VisAxis
            type="y"
            :num-ticks="3"
            :tick-line="false"
            :domain-line="false"
            :tick-format="formatCompactNumber"
          />
          <ChartTooltip />
          <ChartCrosshair :template="tooltipTemplate" :color="variant === 'line' ? color : '#0000'" />
        </VisXYContainer>
      </ChartContainer>
    </CardContent>
  </Card>
</template>

<script setup>
import { CurveType } from "@unovis/ts";
import { VisAxis, VisGroupedBar, VisLine, VisXYContainer } from "@unovis/vue";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  ChartContainer,
  ChartCrosshair,
  ChartTooltip,
  ChartTooltipContent,
  componentToString,
} from "@/components/ui/chart";
import { Skeleton } from "@/components/ui/skeleton";

const props = defineProps({
  data: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  variant: { type: String, default: "bar" },
  dataKey: { type: String, required: true },
  metricLabel: { type: String, required: true },
  color: { type: String, required: true },
  title: { type: String, required: true },
  description: { type: String, required: true },
  emptyText: { type: String, default: "No Google Analytics data for this project yet." },
});

const accessor = (d) => d[props.dataKey];

const chartConfig = computed(() => ({
  [props.dataKey]: { label: props.metricLabel, color: props.color },
}));

const chartData = computed(() =>
  (props.data ?? []).map((row) => ({
    ...row,
    timestamp: new Date(`${row.month}-01T00:00:00`).getTime(),
  }))
);

const total = computed(() =>
  chartData.value.reduce((sum, row) => sum + (row[props.dataKey] || 0), 0)
);

const hasData = computed(() => chartData.value.some((row) => (row[props.dataKey] || 0) > 0));

const formatMonthLabel = (d) =>
  new Date(d).toLocaleDateString("en-US", { month: "short" });

const formatCompactNumber = (d) =>
  new Intl.NumberFormat("en-US", { notation: "compact", maximumFractionDigits: 1 }).format(d);

const formatNumber = (d) => new Intl.NumberFormat("en-US").format(d || 0);

const tooltipTemplate = componentToString(chartConfig, ChartTooltipContent, {
  labelFormatter: (d) =>
    new Date(d).toLocaleDateString("en-US", { month: "long", year: "numeric" }),
});
</script>
