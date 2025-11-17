<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import type { ChartConfig } from "@/components/ui/chart";
import {
  ChartContainer,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
} from "@/components/ui/chart";
import { VisDonut, VisSingleContainer } from "@unovis/vue";

const props = defineProps<{
  trafficSources: Array<{
    source: string;
    medium: string;
    sessions: number;
    users: number;
    properties?: Array<{ property_id: string; property_name: string }>;
  }>;
}>();

type Data = {
  label: string;
  value: number;
  fill: string;
};

const chartData = computed<Data[]>(() => {
  if (!props.trafficSources || props.trafficSources.length === 0) return [];

  const colors = [
    "var(--chart-1)",
    "var(--chart-2)",
    "var(--chart-3)",
    "var(--chart-4)",
    "var(--chart-5)",
  ];

  // Take top 10 sources
  return props.trafficSources.slice(0, 10).map((source, index) => ({
    label: `${source.source} / ${source.medium}`,
    value: source.users || 0,
    fill: colors[index % colors.length],
  }));
});

const chartConfig = computed(() => {
  const config: ChartConfig = {};

  chartData.value.forEach((item, index) => {
    config[`source${index}`] = {
      label: item.label,
      color: item.fill,
    };
  });

  return config;
});

const totalUsers = computed(() => {
  return chartData.value.reduce((sum, item) => sum + item.value, 0);
});

const formatTooltipValue = (value: number) => {
  return `${value.toLocaleString()} users`;
};
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle>Traffic Sources</CardTitle>
      <CardDescription> Where your visitors come from </CardDescription>
    </CardHeader>
    <CardContent>
      <ChartContainer
        :config="chartConfig"
        class="mx-auto aspect-square max-h-[250px] min-h-[200px] w-full"
      >
        <VisSingleContainer v-if="chartData.length > 0" :data="chartData">
          <VisDonut
            :value="(d: Data) => d.value"
            :arc-width="70"
            :pad-angle="0.03"
            :corner-radius="5"
            :color="(d: Data) => d.fill"
          >
            <template #central-label>
              <div class="flex flex-col items-center justify-center">
                <span class="text-2xl font-semibold">{{ totalUsers.toLocaleString() }}</span>
                <span class="text-muted-foreground text-xs">Total Users</span>
              </div>
            </template>
          </VisDonut>
          <ChartTooltip>
            <ChartTooltipContent
              :label-formatter="
                (label: string, payload: any) => {
                  const item = payload?.[0]?.payload;
                  return item?.label || label;
                }
              "
              :formatter="formatTooltipValue"
            />
          </ChartTooltip>
        </VisSingleContainer>
        <div v-else class="text-muted-foreground flex items-center justify-center py-8 text-sm">
          No traffic source data available
        </div>

        <ChartLegendContent v-if="chartData.length > 0" class="mt-4" />
      </ChartContainer>
    </CardContent>
  </Card>
</template>
