<script setup>
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
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
import { computed, ref } from "vue";

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
    default: 'Area Chart - Interactive'
  },
  description: {
    type: String,
    default: 'Showing total visitors for the last 3 months'
  },
  enableTimeRange: {
    type: Boolean,
    default: true
  }
});

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
  const referenceDate = new Date(props.data[props.data.length - 1].date);
  const dayCount =
    timeRange.value === "90d"
      ? 90
      : timeRange.value === "30d"
        ? 30
        : timeRange.value === "7d"
          ? 7
          : 1;

  return props.data.filter((item) => {
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
        <CardTitle>{{ title }}</CardTitle>
        <CardDescription>{{ description }}</CardDescription>
      </div>
      <Select v-if="enableTimeRange" v-model="timeRange">
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
      <ChartContainer :config="config">
        <VisXYContainer :data="filterRange" :svg-defs="svgDefs">
          <VisArea
            :x="(d) => d.date"
            :y="[(d) => d.mobile, (d) => d.desktop]"
            :color="(d, i) => ['url(#fillMobile)', 'url(#fillDesktop)'][i]"
            :opacity="0.4"
          />
          <VisLine
            :x="(d) => d.date"
            :y="[(d) => d.mobile, (d) => d.desktop]"
            :color="
              (d, i) => [config.mobile.color, config.desktop.color][i]
            "
            :line-width="2"
          />
          <VisAxis
            type="x"
            :x="(d) => d.date"
            :tick-line="false"
            :domain-line="false"
            :grid-line="false"
            :num-ticks="6"
            :tick-format="
              (d) => {
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
            :tick-format="(d) => `${d}`"
          />
          <ChartTooltip />
          <ChartCrosshair
            :template="componentToString(config, ChartTooltipContent, { labelKey: 'date' })"
            :color="
              (d, i) => [config.mobile.color, config.desktop.color][i % 2]
            "
          />
        </VisXYContainer>
      </ChartContainer>
    </CardContent>
  </Card>
</template>
