<template>
  <Card class="flex flex-col">
    <CardHeader>
      <CardTitle>Inquiries by status</CardTitle>
      <CardDescription>
        <template v-if="loading">Loading…</template>
        <template v-else>{{ total }} total {{ total === 1 ? "inquiry" : "inquiries" }}</template>
      </CardDescription>
    </CardHeader>
    <CardContent class="flex-1">
      <Skeleton v-if="loading" class="h-[250px] w-full" />
      <div
        v-else-if="total === 0"
        class="flex h-[250px] w-full items-center justify-center text-sm text-muted-foreground"
      >
        No inquiries yet.
      </div>
      <div v-else class="flex flex-col gap-y-4">
        <ChartContainer :config="chartConfig" class="mx-auto aspect-square h-[220px]">
          <VisSingleContainer :data="chartData" :margin="{ top: 12, bottom: 12 }">
            <VisDonut
              :value="(d) => d.count"
              :color="(d) => chartConfig[d.status]?.color ?? 'var(--chart-5)'"
              :arc-width="28"
              :central-label="`${total}`"
              :central-sub-label="total === 1 ? 'inquiry' : 'inquiries'"
            />
            <ChartTooltip
              :triggers="{
                [Donut.selectors.segment]: tooltipTemplate,
              }"
            />
          </VisSingleContainer>
        </ChartContainer>
        <ul class="flex flex-wrap justify-center gap-x-4 gap-y-2 text-sm">
          <li
            v-for="item in legendItems"
            :key="item.status"
            class="flex items-center gap-x-2 text-muted-foreground"
          >
            <span class="size-2.5 rounded-full" :style="{ backgroundColor: item.color }" />
            <span>{{ item.label }}</span>
            <span class="text-foreground font-medium">{{ item.count }}</span>
          </li>
        </ul>
      </div>
    </CardContent>
  </Card>
</template>

<script setup>
import { Donut } from "@unovis/ts";
import { VisDonut, VisSingleContainer } from "@unovis/vue";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  componentToString,
} from "@/components/ui/chart";
import { Skeleton } from "@/components/ui/skeleton";

const props = defineProps({
  data: {
    type: Object,
    default: () => ({}),
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const chartConfig = {
  count: {
    label: "Inquiries",
  },
  new: {
    label: "New",
    color: "var(--chart-1)",
  },
  in_progress: {
    label: "In Progress",
    color: "var(--chart-2)",
  },
  completed: {
    label: "Completed",
    color: "var(--chart-3)",
  },
  archived: {
    label: "Archived",
    color: "var(--chart-4)",
  },
};

const STATUS_ORDER = ["new", "in_progress", "completed", "archived"];

const chartData = computed(() =>
  STATUS_ORDER
    .map((status) => ({ status, count: Number(props.data?.[status] ?? 0) }))
    .filter((item) => item.count > 0)
);

const total = computed(() =>
  STATUS_ORDER.reduce((sum, status) => sum + Number(props.data?.[status] ?? 0), 0)
);

const legendItems = computed(() =>
  STATUS_ORDER.map((status) => ({
    status,
    label: chartConfig[status].label,
    color: chartConfig[status].color,
    count: Number(props.data?.[status] ?? 0),
  }))
);

const tooltipTemplate = componentToString(chartConfig, ChartTooltipContent, { hideLabel: true });
</script>
