<template>
  <Card>
    <CardHeader>
      <CardTitle>Inquiries (last 7 days)</CardTitle>
      <CardDescription>{{ dateRangeLabel }}</CardDescription>
    </CardHeader>
    <CardContent>
      <Skeleton v-if="loading" class="h-[250px] w-full" />
      <div
        v-else-if="!hasData"
        class="flex h-[250px] w-full items-center justify-center text-sm text-muted-foreground"
      >
        No inquiries in the last 7 days.
      </div>
      <ChartContainer v-else :config="chartConfig">
        <VisXYContainer :data="chartData" :margin="{ left: -24 }" :y-domain="[0, undefined]">
          <VisGroupedBar
            :x="(d) => d.timestamp"
            :y="(d) => d.count"
            :color="chartConfig.count.color"
            :rounded-corners="10"
          />
          <VisAxis
            type="x"
            :x="(d) => d.timestamp"
            :tick-line="false"
            :domain-line="false"
            :grid-line="false"
            :num-ticks="7"
            :tick-values="chartData.map((d) => d.timestamp)"
            :tick-format="formatDayLabel"
          />
          <VisAxis type="y" :num-ticks="3" :tick-line="false" :domain-line="false" />
          <ChartTooltip />
          <ChartCrosshair :template="tooltipTemplate" color="#0000" />
        </VisXYContainer>
      </ChartContainer>
    </CardContent>
    <CardFooter v-if="!loading && hasData" class="flex-col items-start gap-2 text-sm">
      <div class="flex items-center gap-2 font-medium tracking-tight">
        {{ total }} inquiries this week
        <Icon :name="trendIcon" class="size-4" />
      </div>
      <div class="tracking-tight text-muted-foreground">
        Showing inquiries submitted in the last 7 days
      </div>
    </CardFooter>
  </Card>
</template>

<script setup>
import { VisAxis, VisGroupedBar, VisXYContainer } from "@unovis/vue";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  ChartContainer,
  ChartCrosshair,
  ChartTooltip,
  ChartTooltipContent,
  componentToString,
} from "@/components/ui/chart";
import { Skeleton } from "@/components/ui/skeleton";

const props = defineProps({
  data: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const chartConfig = {
  count: {
    label: "Inquiries",
    color: "var(--chart-1)",
  },
};

const chartData = computed(() =>
  (props.data ?? []).map((row) => ({
    ...row,
    timestamp: new Date(`${row.date}T00:00:00`).getTime(),
  }))
);

const total = computed(() => chartData.value.reduce((sum, row) => sum + (row.count || 0), 0));

const hasData = computed(() => chartData.value.length > 0);

const dateRangeLabel = computed(() => {
  if (!chartData.value.length) return "";
  const first = new Date(chartData.value[0].timestamp);
  const last = new Date(chartData.value[chartData.value.length - 1].timestamp);
  const formatter = (date, opts) => date.toLocaleDateString("en-US", opts);
  const sameYear = first.getFullYear() === last.getFullYear();
  return `${formatter(first, { month: "short", day: "numeric" })} - ${formatter(last, sameYear ? { month: "short", day: "numeric", year: "numeric" } : { month: "short", day: "numeric", year: "numeric" })}`;
});

const trendIcon = computed(() => {
  const half = Math.floor(chartData.value.length / 2);
  const first = chartData.value.slice(0, half).reduce((s, r) => s + r.count, 0);
  const last = chartData.value.slice(half).reduce((s, r) => s + r.count, 0);
  return last >= first ? "hugeicons:chart-increase" : "hugeicons:chart-decrease";
});

const formatDayLabel = (d) => {
  const date = new Date(d);
  return date.toLocaleDateString("en-US", { weekday: "short", day: "numeric" });
};

const tooltipTemplate = componentToString(chartConfig, ChartTooltipContent, {
  labelFormatter: (d) => {
    const date = new Date(d);
    return date.toLocaleDateString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
    });
  },
});
</script>
