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
  ChartCrosshair,
  ChartTooltip,
  ChartTooltipContent,
  componentToString,
} from "@/components/ui/chart";
import { Orientation } from "@unovis/ts";
import { VisAxis, VisGroupedBar, VisXYContainer } from "@unovis/vue";
import { TrendingUp } from "lucide-vue-next";

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
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle>Bar Chart - Horizontal</CardTitle>
      <CardDescription>January - June 2024</CardDescription>
    </CardHeader>
    <CardContent>
      <ChartContainer :config="config">
        <VisXYContainer :data="data">
          <VisGroupedBar
            :x="(d) => d.date"
            :y="(d) => d.desktop"
            :color="config.desktop.color"
            :rounded-corners="5"
            :orientation="Orientation.Horizontal"
          />
          <VisAxis
            type="y"
            :tick-line="false"
            :domain-line="false"
            :grid-line="false"
            :num-ticks="6"
            :tick-format="
              (d) => {
                const date = new Date(d);
                return date.toLocaleDateString('en-US', {
                  month: 'short',
                });
              }
            "
            :tick-values="data.map((d) => d.date)"
          />
          <ChartTooltip />
          <ChartCrosshair
            :template="componentToString(config, ChartTooltipContent, { hideLabel: true })"
            color="#0000"
          />
        </VisXYContainer>
      </ChartContainer>
    </CardContent>
    <CardFooter class="flex-col items-start gap-2 text-sm">
      <div class="flex gap-2 leading-none font-medium">
        Trending up by 5.2% this month <TrendingUp class="h-4 w-4" />
      </div>
      <div class="text-muted-foreground leading-none">
        Showing total visitors for the last 6 months
      </div>
    </CardFooter>
  </Card>
</template>
