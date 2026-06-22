<template>
  <div class="bg-card text-card-foreground w-full overflow-hidden rounded-xl border">
    <div class="flex items-center gap-2 border-b px-6 py-4">
      <div class="grid flex-1 gap-1">
        <div v-if="title" class="text-base font-semibold tracking-tight">{{ title }}</div>
        <div v-if="description" class="text-muted-foreground text-sm tracking-tight">
          {{ description }}
        </div>
      </div>
      <Select v-model="timeRange">
        <SelectTrigger class="w-[150px] rounded-lg sm:w-[160px]" aria-label="Select a range">
          <SelectValue :placeholder="rangeOptions[0].label" />
        </SelectTrigger>
        <SelectContent class="rounded-xl">
          <SelectItem
            v-for="opt in rangeOptions"
            :key="opt.value"
            :value="opt.value"
            class="rounded-lg"
          >
            {{ opt.label }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>
    <div class="px-2 pt-4 pb-2 sm:px-6">
      <ChartContainer :config="config" class="aspect-auto h-[250px] w-full">
        <VisXYContainer
          :data="filtered"
          :svg-defs="svgDefs"
          :margin="{ left: -4 }"
          :y-domain="[0, undefined]"
        >
          <VisArea
            :x="(d) => d[xKey]"
            :y="areaY"
            :color="areaColor"
            :opacity="0.5"
            :curve-type="CurveType.Natural"
          />
          <VisLine
            :x="(d) => d[xKey]"
            :y="areaY"
            :color="lineColor"
            :line-width="1.5"
            :curve-type="CurveType.Natural"
          />
          <VisAxis
            type="x"
            :x="(d) => d[xKey]"
            :tick-line="false"
            :domain-line="false"
            :grid-line="false"
            :num-ticks="6"
            :tick-format="xFormat"
          />
          <VisAxis
            type="y"
            :num-ticks="3"
            :tick-line="false"
            :domain-line="false"
            :tick-format="yFormat"
          />
          <ChartTooltip />
          <ChartCrosshair :template="tooltipTemplate" :color="crosshairColor" />
        </VisXYContainer>
        <ChartLegendContent />
      </ChartContainer>
    </div>
  </div>
</template>

<script setup>
import { VisArea, VisAxis, VisLine, VisXYContainer } from "@unovis/vue";
import { CurveType } from "@unovis/ts";
import {
  ChartContainer,
  ChartCrosshair,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
  componentToString,
} from ".";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

const props = defineProps({
  data: {
    type: Array,
    required: true,
  },
  config: {
    type: Object,
    required: true,
  },
  dataKeys: {
    type: Array,
    default: null,
  },
  xKey: {
    type: String,
    default: "date",
  },
  title: {
    type: String,
    default: "",
  },
  description: {
    type: String,
    default: "",
  },
});

const rangeOptions = [
  { value: "90d", label: "Last 3 months", days: 90 },
  { value: "30d", label: "Last 30 days", days: 30 },
  { value: "7d", label: "Last 7 days", days: 7 },
];

const timeRange = ref("90d");

const keys = computed(() =>
  props.dataKeys && props.dataKeys.length
    ? props.dataKeys
    : Object.keys(props.config).filter((key) => props.config[key]?.color)
);

const latestDate = computed(() => {
  const times = props.data
    .map((d) => new Date(d[props.xKey]).getTime())
    .filter((t) => !Number.isNaN(t));
  return times.length ? new Date(Math.max(...times)) : new Date();
});

const filtered = computed(() => {
  const days = rangeOptions.find((o) => o.value === timeRange.value)?.days ?? 90;
  const start = new Date(latestDate.value);
  start.setDate(start.getDate() - days);
  return props.data.filter((d) => new Date(d[props.xKey]) >= start);
});

const colorList = computed(() => keys.value.map((key) => props.config[key]?.color || "var(--chart-1)"));

const areaY = computed(() => {
  const accessors = keys.value.map((key) => (d) => d[key]);
  return accessors.length === 1 ? accessors[0] : accessors;
});

const areaColor = computed(() => {
  const colors = keys.value.map((key) => `url(#fill-${key})`);
  return colors.length === 1 ? colors[0] : colors;
});

const lineColor = computed(() => (colorList.value.length === 1 ? colorList.value[0] : colorList.value));

const crosshairColor = (d, i) => colorList.value[i % colorList.value.length];

const svgDefs = computed(() =>
  keys.value
    .map((key) => {
      const color = props.config[key]?.color || "var(--chart-1)";
      return `<linearGradient id="fill-${key}" x1="0" y1="0" x2="0" y2="1"><stop offset="5%" stop-color="${color}" stop-opacity="0.8" /><stop offset="95%" stop-color="${color}" stop-opacity="0.1" /></linearGradient>`;
    })
    .join("")
);

const xFormat = (d) =>
  new Date(d).toLocaleDateString("en-US", { month: "short", day: "numeric" });

const yFormat = (d) =>
  new Intl.NumberFormat("en-US", { notation: "compact", maximumFractionDigits: 1 }).format(d);

const currentConfig = computed(() => props.config);

const tooltipTemplate = componentToString(currentConfig, ChartTooltipContent, {
  labelFormatter: (d) =>
    new Date(d).toLocaleDateString("en-US", { month: "short", day: "numeric" }),
});
</script>
