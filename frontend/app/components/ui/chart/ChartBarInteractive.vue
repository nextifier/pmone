<template>
  <div class="bg-card text-card-foreground w-full overflow-hidden rounded-xl border">
    <div class="flex flex-col items-stretch border-b sm:flex-row">
      <div class="flex flex-1 flex-col justify-center gap-1 px-6 py-4">
        <div v-if="title" class="text-base font-semibold tracking-tight">{{ title }}</div>
        <div v-if="description" class="text-muted-foreground text-sm tracking-tight">
          {{ description }}
        </div>
      </div>
      <div class="flex">
        <button
          v-for="key in keys"
          :key="key"
          type="button"
          :data-active="activeChart === key"
          class="data-[active=true]:bg-muted/50 flex flex-1 flex-col justify-center gap-1 border-t px-6 py-3 text-left transition-colors even:border-l sm:border-t-0 sm:border-l sm:px-8"
          @click="activeChart = key"
        >
          <span class="text-muted-foreground text-xs tracking-tight">
            {{ config[key]?.label || key }}
          </span>
          <span class="text-lg leading-none font-semibold tracking-tighter sm:text-2xl">
            {{ totals[key].toLocaleString() }}
          </span>
        </button>
      </div>
    </div>
    <div class="px-2 pt-4 pb-2 sm:px-6">
      <ChartContainer :config="config" class="aspect-auto h-[250px] w-full" cursor>
        <VisXYContainer :data="data" :margin="{ left: -4 }" :y-domain="[0, undefined]">
          <VisGroupedBar
            :x="(d) => d[xKey]"
            :y="(d) => d[activeChart]"
            :color="activeColor"
            bar-padding="0.1"
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
          <ChartCrosshair :template="tooltipTemplate" color="#0000" />
        </VisXYContainer>
      </ChartContainer>
    </div>
  </div>
</template>

<script setup>
import { VisAxis, VisGroupedBar, VisXYContainer } from "@unovis/vue";
import {
  ChartContainer,
  ChartCrosshair,
  ChartTooltip,
  ChartTooltipContent,
  componentToString,
} from ".";

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

const keys = computed(() =>
  props.dataKeys && props.dataKeys.length
    ? props.dataKeys
    : Object.keys(props.config).filter((key) => props.config[key]?.color)
);

const activeChart = ref(keys.value[0]);

const totals = computed(() =>
  Object.fromEntries(
    keys.value.map((key) => [key, props.data.reduce((acc, d) => acc + (Number(d[key]) || 0), 0)])
  )
);

const activeColor = computed(() => props.config[activeChart.value]?.color || "var(--chart-1)");

const xFormat = (d) =>
  new Date(d).toLocaleDateString("en-US", { month: "short", day: "numeric" });

const yFormat = (d) =>
  new Intl.NumberFormat("en-US", { notation: "compact", maximumFractionDigits: 1 }).format(d);

const currentConfig = computed(() => props.config);

const tooltipTemplate = componentToString(currentConfig, ChartTooltipContent, {
  labelFormatter: (d) =>
    new Date(d).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" }),
});
</script>
