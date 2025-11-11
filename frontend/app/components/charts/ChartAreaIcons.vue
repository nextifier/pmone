<script setup>
import { VisArea, VisAxis, VisLine, VisXYContainer } from "@unovis/vue";
import { TrendingDown, TrendingUp } from "lucide-vue-next";

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
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
  componentToString,
} from "@/components/ui/chart";

const props = defineProps({
  data: {
    type: Array,
    required: true
  },
  config: {
    type: Object,
    required: true
  },
  title: {
    type: String,
    default: 'Area Chart - Icons'
  },
  description: {
    type: String,
    default: 'Showing total visitors for the last 6 months'
  },
  footerText: {
    type: String,
    default: 'Trending up by 5.2% this month'
  },
  footerIcon: {
    type: Object,
    default: () => TrendingUp
  },
  footerSubtext: {
    type: String,
    default: 'January - June 2024'
  }
});
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle>{{ title }}</CardTitle>
      <CardDescription>{{ description }}</CardDescription>
    </CardHeader>
    <CardContent>
      <ChartContainer :config="config">
        <VisXYContainer :data="data" :margin="{ top: 10, bottom: 10 }">
          <VisArea
            :x="(d) => d.month"
            :y="[(d) => d.mobile, (d) => d.desktop]"
            :color="
              (d, i) => [config.mobile.color, config.desktop.color][i]
            "
            :opacity="0.4"
          />
          <VisLine
            :x="(d) => d.month"
            :y="[(d) => d.mobile, (d) => d.mobile + d.desktop]"
            :color="
              (d, i) => [config.mobile.color, config.desktop.color][i]
            "
            :line-width="1"
          />
          <VisAxis
            type="x"
            :x="(d) => d.month"
            :tick-line="false"
            :domain-line="false"
            :grid-line="false"
            :num-ticks="6"
            :tick-format="
              (d, index) => {
                return data[index].monthLabel.slice(0, 3);
              }
            "
          />
          <VisAxis
            type="y"
            :num-ticks="3"
            :tick-line="false"
            :domain-line="false"
            :tick-format="(d, index) => ''"
          />
          <ChartTooltip />
          <ChartCrosshair
            :template="
              componentToString(config, ChartTooltipContent, {
                labelKey: 'monthLabel',
                indicator: 'line',
              })
            "
            :color="
              (d, i) => [config.mobile.color, config.desktop.color][i % 2]
            "
          />
        </VisXYContainer>

        <ChartLegendContent />
      </ChartContainer>
    </CardContent>
    <CardFooter>
      <div class="flex w-full items-start gap-2 text-sm">
        <div class="grid gap-2">
          <div class="flex items-center gap-2 leading-none font-medium">
            {{ footerText }} <component :is="footerIcon" class="h-4 w-4" />
          </div>
          <div class="text-muted-foreground flex items-center gap-2 leading-none">
            {{ footerSubtext }}
          </div>
        </div>
      </div>
    </CardFooter>
  </Card>
</template>
