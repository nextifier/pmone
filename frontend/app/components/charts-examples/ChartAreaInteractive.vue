<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import type { ChartConfig } from "@/components/ui/chart";
import {
  ChartContainer,
  ChartCrosshair,
  ChartTooltip,
  ChartTooltipContent,
  componentToString,
} from "@/components/ui/chart";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { VisArea, VisAxis, VisLine, VisXYContainer } from "@unovis/vue";

const description = "An interactive area chart";

const chartData = [
  { date: new Date("2024-04-01"), desktop: 222, mobile: 150 },
  { date: new Date("2024-04-02"), desktop: 97, mobile: 180 },
  { date: new Date("2024-04-03"), desktop: 167, mobile: 120 },
  { date: new Date("2024-04-04"), desktop: 242, mobile: 260 },
  { date: new Date("2024-04-05"), desktop: 373, mobile: 290 },
  { date: new Date("2024-04-06"), desktop: 301, mobile: 340 },
  { date: new Date("2024-04-07"), desktop: 245, mobile: 180 },
  { date: new Date("2024-04-08"), desktop: 409, mobile: 320 },
  { date: new Date("2024-04-09"), desktop: 59, mobile: 110 },
  { date: new Date("2024-04-10"), desktop: 261, mobile: 190 },
  { date: new Date("2024-04-11"), desktop: 327, mobile: 330 },
  { date: new Date("2024-04-12"), desktop: 292, mobile: 210 },
  { date: new Date("2024-04-13"), desktop: 342, mobile: 380 },
  { date: new Date("2024-04-14"), desktop: 137, mobile: 220 },
  { date: new Date("2024-04-15"), desktop: 120, mobile: 170 },
  { date: new Date("2024-04-16"), desktop: 138, mobile: 190 },
  { date: new Date("2024-04-17"), desktop: 446, mobile: 360 },
  { date: new Date("2024-04-18"), desktop: 364, mobile: 410 },
  { date: new Date("2024-04-19"), desktop: 243, mobile: 180 },
  { date: new Date("2024-04-20"), desktop: 89, mobile: 150 },
  { date: new Date("2024-04-21"), desktop: 137, mobile: 200 },
  { date: new Date("2024-04-22"), desktop: 224, mobile: 170 },
  { date: new Date("2024-04-23"), desktop: 138, mobile: 230 },
  { date: new Date("2024-04-24"), desktop: 387, mobile: 290 },
  { date: new Date("2024-04-25"), desktop: 215, mobile: 250 },
  { date: new Date("2024-04-26"), desktop: 75, mobile: 130 },
  { date: new Date("2024-04-27"), desktop: 383, mobile: 330 },
  { date: new Date("2024-04-28"), desktop: 122, mobile: 210 },
  { date: new Date("2024-04-29"), desktop: 315, mobile: 270 },
  { date: new Date("2024-04-30"), desktop: 235, mobile: 190 },
  { date: new Date("2024-05-01"), desktop: 177, mobile: 100 },
  { date: new Date("2024-05-02"), desktop: 82, mobile: 155 },
  { date: new Date("2024-05-03"), desktop: 252, mobile: 200 },
  { date: new Date("2024-05-04"), desktop: 294, mobile: 220 },
  { date: new Date("2024-05-05"), desktop: 147, mobile: 170 },
  { date: new Date("2024-05-06"), desktop: 247, mobile: 270 },
  { date: new Date("2024-05-07"), desktop: 108, mobile: 210 },
  { date: new Date("2024-05-08"), desktop: 191, mobile: 290 },
  { date: new Date("2024-05-09"), desktop: 335, mobile: 250 },
  { date: new Date("2024-05-10"), desktop: 197, mobile: 220 },
  { date: new Date("2024-05-11"), desktop: 70, mobile: 240 },
  { date: new Date("2024-05-12"), desktop: 290, mobile: 130 },
  { date: new Date("2024-05-13"), desktop: 246, mobile: 200 },
  { date: new Date("2024-05-14"), desktop: 195, mobile: 150 },
  { date: new Date("2024-05-15"), desktop: 159, mobile: 120 },
  { date: new Date("2024-05-16"), desktop: 355, mobile: 170 },
  { date: new Date("2024-05-17"), desktop: 129, mobile: 80 },
  { date: new Date("2024-05-18"), desktop: 340, mobile: 200 },
  { date: new Date("2024-05-19"), desktop: 23, mobile: 120 },
  { date: new Date("2024-05-20"), desktop: 85, mobile: 80 },
  { date: new Date("2024-05-21"), desktop: 217, mobile: 270 },
  { date: new Date("2024-05-22"), desktop: 237, mobile: 210 },
  { date: new Date("2024-05-23"), desktop: 206, mobile: 180 },
  { date: new Date("2024-05-24"), desktop: 215, mobile: 270 },
  { date: new Date("2024-05-25"), desktop: 201, mobile: 250 },
  { date: new Date("2024-05-26"), desktop: 117, mobile: 230 },
  { date: new Date("2024-05-27"), desktop: 142, mobile: 240 },
  { date: new Date("2024-05-28"), desktop: 213, mobile: 290 },
  { date: new Date("2024-05-29"), desktop: 191, mobile: 200 },
  { date: new Date("2024-05-30"), desktop: 179, mobile: 100 },
  { date: new Date("2024-05-31"), desktop: 75, mobile: 228 },
  { date: new Date("2024-06-01"), desktop: 383, mobile: 355 },
  { date: new Date("2024-06-02"), desktop: 101, mobile: 171 },
  { date: new Date("2024-06-03"), desktop: 323, mobile: 201 },
  { date: new Date("2024-06-04"), desktop: 233, mobile: 247 },
  { date: new Date("2024-06-05"), desktop: 136, mobile: 247 },
  { date: new Date("2024-06-06"), desktop: 252, mobile: 290 },
  { date: new Date("2024-06-07"), desktop: 292, mobile: 330 },
  { date: new Date("2024-06-08"), desktop: 382, mobile: 350 },
  { date: new Date("2024-06-09"), desktop: 321, mobile: 271 },
  { date: new Date("2024-06-10"), desktop: 233, mobile: 187 },
  { date: new Date("2024-06-11"), desktop: 105, mobile: 139 },
  { date: new Date("2024-06-12"), desktop: 214, mobile: 290 },
  { date: new Date("2024-06-13"), desktop: 188, mobile: 250 },
  { date: new Date("2024-06-14"), desktop: 239, mobile: 210 },
  { date: new Date("2024-06-15"), desktop: 322, mobile: 310 },
  { date: new Date("2024-06-16"), desktop: 165, mobile: 270 },
  { date: new Date("2024-06-17"), desktop: 171, mobile: 299 },
  { date: new Date("2024-06-18"), desktop: 214, mobile: 315 },
  { date: new Date("2024-06-19"), desktop: 182, mobile: 210 },
  { date: new Date("2024-06-20"), desktop: 242, mobile: 285 },
  { date: new Date("2024-06-21"), desktop: 191, mobile: 197 },
  { date: new Date("2024-06-22"), desktop: 201, mobile: 202 },
  { date: new Date("2024-06-23"), desktop: 344, mobile: 340 },
  { date: new Date("2024-06-24"), desktop: 383, mobile: 370 },
  { date: new Date("2024-06-25"), desktop: 392, mobile: 313 },
  { date: new Date("2024-06-26"), desktop: 201, mobile: 245 },
  { date: new Date("2024-06-27"), desktop: 193, mobile: 229 },
  { date: new Date("2024-06-28"), desktop: 286, mobile: 290 },
  { date: new Date("2024-06-29"), desktop: 289, mobile: 342 },
  { date: new Date("2024-06-30"), desktop: 340, mobile: 297 },
];

type Data = (typeof chartData)[number];

const chartConfig = {
  mobile: {
    label: "Mobile",
    color: "var(--chart-2)",
  },
  desktop: {
    label: "Desktop",
    color: "var(--chart-1)",
  },
} satisfies ChartConfig;

const svgDefs = `
  <linearGradient id="fillDesktop" x1="0" y1="0" x2="0" y2="1">
    <stop offset="5%" stop-color="var(--color-desktop)" stop-opacity="0.8" />
    <stop offset="95%" stop-color="var(--color-desktop)" stop-opacity="0.1" />
  </linearGradient>
  <linearGradient id="fillMobile" x1="0" y1="0" x2="0" y2="1">
    <stop offset="5%" stop-color="var(--color-mobile)" stop-opacity="0.8" />
    <stop offset="95%" stop-color="var(--color-mobile)" stop-opacity="0.1" />
  </linearGradient>
`;

const timeRange = ref("90d");

const filterRange = computed(() => {
  // Use the last date in the data as reference instead of today
  const referenceDate = new Date("2024-06-30");
  const dayCount =
    timeRange.value === "90d"
      ? 90
      : timeRange.value === "30d"
        ? 30
        : timeRange.value === "7d"
          ? 7
          : 1;

  return chartData.filter((item) => {
    const date = item.date;
    const diff = (referenceDate.getTime() - date.getTime()) / (1000 * 60 * 60 * 24);
    return diff >= 0 && diff <= dayCount;
  });
});
</script>

<template>
  <Card>
    <CardHeader class="flex flex-row items-center border-b py-4">
      <div class="flex flex-1 flex-col gap-2">
        <CardTitle>Area Chart - Interactive</CardTitle>
        <CardDescription> Showing total visitors for the last 3 months </CardDescription>
      </div>
      <Select v-model="timeRange">
        <SelectTrigger class="w-[160px] rounded-lg">
          <SelectValue />
        </SelectTrigger>
        <SelectContent class="rounded-xl">
          <SelectItem value="90d">Last 3 months</SelectItem>
          <SelectItem value="30d">Last 30 days</SelectItem>
          <SelectItem value="7d">Last 7 days</SelectItem>
        </SelectContent>
      </Select>
    </CardHeader>
    <CardContent class="px-2 pt-4 sm:px-6 sm:pt-6">
      <ChartContainer :config="chartConfig">
        <VisXYContainer :data="filterRange" :svg-defs="svgDefs">
          <VisArea
            :x="(d: Data) => d.date"
            :y="[(d: Data) => d.mobile, (d: Data) => d.desktop]"
            :color="(d: Data, i: number) => ['url(#fillMobile)', 'url(#fillDesktop)'][i]"
            :opacity="0.4"
          />
          <VisLine
            :x="(d: Data) => d.date"
            :y="[(d: Data) => d.mobile, (d: Data) => d.desktop]"
            :color="
              (d: Data, i: number) => [chartConfig.mobile.color, chartConfig.desktop.color][i]
            "
            :line-width="2"
          />
          <VisAxis
            type="x"
            :x="(d: Data) => d.date"
            :tick-line="false"
            :domain-line="false"
            :grid-line="false"
            :num-ticks="6"
            :tick-format="
              (d: number) => {
                const date = new Date(d);
                return date.toLocaleDateString('en-US', {
                  month: 'short',
                  day: 'numeric',
                });
              }
            "
          />
          <VisAxis
            type="y"
            :num-ticks="3"
            :tick-line="false"
            :domain-line="false"
            :tick-format="(d: number) => `${d}`"
          />
          <ChartTooltip />
          <ChartCrosshair
            :template="componentToString(chartConfig, ChartTooltipContent, { labelKey: 'date' })"
            :color="
              (d: Data, i: number) => [chartConfig.mobile.color, chartConfig.desktop.color][i % 2]
            "
          />
        </VisXYContainer>
      </ChartContainer>
    </CardContent>
  </Card>
</template>
