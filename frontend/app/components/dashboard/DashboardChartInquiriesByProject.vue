<template>
  <Card class="flex flex-col">
    <CardHeader class="items-center pb-0">
      <CardTitle>Inquiries by project</CardTitle>
      <CardDescription>Top {{ chartData.length || 4 }} projects by all-time inquiries</CardDescription>
    </CardHeader>
    <CardContent class="flex-1 pb-0">
      <Skeleton v-if="loading" class="mx-auto h-[250px] w-[250px] rounded-full" />
      <div
        v-else-if="chartData.length === 0"
        class="flex h-[250px] w-full items-center justify-center text-sm text-muted-foreground"
      >
        No inquiries yet.
      </div>
      <ChartContainer
        v-else
        :config="chartConfig"
        class="mx-auto aspect-square max-h-[250px]"
        :style="{
          '--vis-donut-central-label-font-size': 'var(--text-2xl)',
          '--vis-donut-central-label-font-weight': 'var(--font-weight-semibold)',
          '--vis-donut-central-label-text-color': 'var(--foreground)',
          '--vis-donut-central-sub-label-text-color': 'var(--muted-foreground)',
        }"
      >
        <VisSingleContainer :data="chartData" :margin="{ top: 16, bottom: 16 }">
          <VisDonut
            :value="(d) => d.count"
            :color="(d) => colorFor(d.project_id)"
            :arc-width="30"
            :central-label-offset-y="8"
            :central-label="total.toLocaleString('en-US')"
            central-sub-label="inquiries"
          />
          <ChartTooltip
            :triggers="{
              [Donut.selectors.segment]: tooltipTemplate,
            }"
          />
        </VisSingleContainer>
      </ChartContainer>
    </CardContent>
    <CardFooter v-if="!loading && chartData.length > 0" class="flex-col gap-2 text-sm">
      <div class="flex items-center gap-2 font-medium tracking-tight">
        {{ leaderLabel }}
        <Icon name="hugeicons:chart-increase" class="size-4" />
      </div>
      <ul class="flex flex-wrap justify-center gap-x-4 gap-y-1 tracking-tight text-muted-foreground">
        <li
          v-for="item in chartData"
          :key="item.project_id"
          class="flex items-center gap-x-2"
        >
          <span class="size-2.5 rounded-full" :style="{ backgroundColor: colorFor(item.project_id) }" />
          <span>{{ truncate(item.name) }}</span>
          <span class="text-foreground font-medium">{{ item.count.toLocaleString("en-US") }}</span>
        </li>
      </ul>
    </CardFooter>
  </Card>
</template>

<script setup>
import { Donut } from "@unovis/ts";
import { VisDonut, VisSingleContainer } from "@unovis/vue";
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

const PALETTE = [
  "var(--chart-1)",
  "var(--chart-2)",
  "var(--chart-3)",
  "var(--chart-4)",
  "var(--chart-5)",
];

const chartData = computed(() =>
  (props.data ?? []).map((item) => ({
    project_id: Number(item.project_id),
    name: String(item.name ?? "Unknown"),
    count: Number(item.count ?? 0),
  }))
);

const total = computed(() =>
  chartData.value.reduce((sum, item) => sum + item.count, 0)
);

const leaderLabel = computed(() => {
  const top = chartData.value[0];
  if (!top) return "";
  return `${top.name} leads with ${top.count.toLocaleString("en-US")} inquiries`;
});

const colorFor = (projectId) => {
  const index = chartData.value.findIndex((item) => item.project_id === projectId);
  return PALETTE[index] ?? "var(--chart-5)";
};

const truncate = (value) => {
  if (!value) return "";
  return value.length > 18 ? `${value.slice(0, 16)}…` : value;
};

const chartConfig = computed(() => {
  const config = { count: { label: "Inquiries" } };
  chartData.value.forEach((item, index) => {
    config[item.project_id] = {
      label: item.name,
      color: PALETTE[index] ?? "var(--chart-5)",
    };
  });
  return config;
});

const tooltipTemplate = computed(() =>
  componentToString(chartConfig.value, ChartTooltipContent, { hideLabel: true })
);
</script>
