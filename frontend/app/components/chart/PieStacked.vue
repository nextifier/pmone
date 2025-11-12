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

const props = defineProps({
  desktopData: {
    type: Array,
    required: true,
  },
  mobileData: {
    type: Array,
    required: true,
  },
  config: {
    type: Object,
    required: true,
  },
});
</script>

<template>
  <Card class="flex flex-col">
    <CardHeader class="items-center pb-0">
      <CardTitle>Pie Chart - Stacked</CardTitle>
      <CardDescription>January - May 2024</CardDescription>
    </CardHeader>
    <CardContent class="flex-1 pb-0">
      <ChartContainer
        :config="config"
        class="relative [&_[data-vis-single-container]]:!absolute"
      >
        <VisSingleContainer :margin="{ top: 30, bottom: 30 }">
          <VisDonut
            :data="mobileData"
            :value="(d) => d.mobile"
            :color="(d) => config[d.month].color"
            :arc-width="25"
          />
          <ChartTooltip
            :triggers="{
              [Donut.selectors.segment]: componentToString(config, ChartTooltipContent, {
                hideLabel: true,
              }),
            }"
          />
        </VisSingleContainer>
        <VisSingleContainer :margin="{ top: 30, bottom: 30 }">
          <VisDonut
            :data="desktopData"
            :value="(d) => d.desktop"
            :color="(d) => config[d.month].color"
            :arc-width="0"
            :radius="50"
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
        Showing total visitors for the last 5 months
      </div>
    </CardFooter>
  </Card>
</template>
