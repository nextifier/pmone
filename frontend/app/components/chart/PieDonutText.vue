<script setup>
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
import { Donut } from "@unovis/ts";
import { VisDonut, VisSingleContainer } from "@unovis/vue";
import { TrendingUp } from "lucide-vue-next";
import { computed } from "vue";

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

const totalVisitors = computed(() => data.reduce((acc, curr) => acc + curr.visitors, 0));
</script>

<template>
  <Card class="flex flex-col">
    <CardHeader class="items-center pb-0">
      <CardTitle>Pie Chart</CardTitle>
      <CardDescription>January - June 2024</CardDescription>
    </CardHeader>
    <CardContent class="flex-1 pb-0">
      <ChartContainer
        :config="config"
        :style="{
          '--vis-donut-central-label-font-size': 'var(--text-3xl)',
          '--vis-donut-central-label-font-weight': 'var(--font-weight-bold)',
          '--vis-donut-central-label-text-color': 'var(--foreground)',
          '--vis-donut-central-sub-label-text-color': 'var(--muted-foreground)',
        }"
      >
        <VisSingleContainer :data="data" :margin="{ top: 30, bottom: 30 }">
          <VisDonut
            :value="(d) => d.visitors"
            :color="(d) => config[d.browser].color"
            :arc-width="30"
            :central-label-offset-y="10"
            :central-label="totalVisitors.toLocaleString()"
            central-sub-label="Visitors"
          />
          <ChartTooltip
            :triggers="{
              [Donut.selectors.segment]: componentToString(config, ChartTooltipContent, {
                hideLabel: true,
              }),
            }"
          />
        </VisSingleContainer>
      </ChartContainer>
    </CardContent>
    <CardFooter class="flex-col gap-2 text-sm">
      <div class="flex items-center gap-2 leading-none font-medium">
        Trending up by 5.2% this month <TrendingUp class="h-4 w-4" />
      </div>
      <div class="text-muted-foreground leading-none">
        Showing total visitors for the last 6 months
      </div>
    </CardFooter>
  </Card>
</template>
