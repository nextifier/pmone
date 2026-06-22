<template>
  <ChartContainer :config="config" :class="containerClass" :style="centerStyle">
    <template v-if="resolvedLayers">
      <VisSingleContainer
        v-for="(layer, idx) in resolvedLayers"
        :key="idx"
        :data="layer.data"
        :margin="{ top: 20, bottom: 20 }"
      >
        <VisDonut
          :value="(d) => d[layer.valueKey]"
          :color="(d) => config[d[nameKey]]?.color || 'var(--chart-1)'"
          :arc-width="layer.arcWidth"
          :radius="layer.radius || undefined"
        />
        <ChartTooltip :triggers="tooltipTriggers" />
      </VisSingleContainer>
    </template>
    <VisSingleContainer v-else :data="data" :margin="{ top: 20, bottom: 20 }">
      <VisDonut
        :value="(d) => d[valueKey]"
        :color="(d) => config[d[nameKey]]?.color || 'var(--chart-1)'"
        :arc-width="arcWidth"
        :central-label="resolvedCenterLabel || ''"
        :central-sub-label="centerSubLabel || ''"
        :central-label-offset-y="centerSubLabel ? 10 : 0"
      />
      <ChartTooltip :triggers="tooltipTriggers" />
    </VisSingleContainer>
    <ChartLegendContent v-if="legend" />
  </ChartContainer>
</template>

<script setup>
import { Donut } from "@unovis/ts";
import { VisDonut, VisSingleContainer } from "@unovis/vue";
import {
  ChartContainer,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
  componentToString,
} from ".";

const props = defineProps({
  data: {
    type: Array,
    default: () => [],
  },
  config: {
    type: Object,
    required: true,
  },
  valueKey: {
    type: String,
    default: "value",
  },
  nameKey: {
    type: String,
    default: "name",
  },
  // 0 renders a full pie; any positive value renders a donut with that ring width.
  arcWidth: {
    type: Number,
    default: 0,
  },
  centerLabel: {
    type: String,
    default: null,
  },
  centerSubLabel: {
    type: String,
    default: null,
  },
  total: {
    type: [Number, String],
    default: null,
  },
  legend: {
    type: Boolean,
    default: false,
  },
  // Concentric rings for a stacked/nested pie. Each entry:
  // { data, valueKey, arcWidth, radius }.
  layers: {
    type: Array,
    default: null,
  },
});

const totalSum = computed(() =>
  props.data.reduce((acc, d) => acc + (Number(d[props.valueKey]) || 0), 0)
);

const resolvedCenterLabel = computed(() => {
  if (props.centerLabel) {
    return props.centerLabel;
  }
  if (props.total !== null) {
    return typeof props.total === "number" ? props.total.toLocaleString() : props.total;
  }
  if (props.centerSubLabel) {
    return totalSum.value.toLocaleString();
  }
  return null;
});

const resolvedLayers = computed(() =>
  props.layers && props.layers.length
    ? props.layers.map((layer) => ({
        arcWidth: 25,
        radius: null,
        valueKey: props.valueKey,
        ...layer,
      }))
    : null
);

const containerClass = computed(() => {
  const base = "mx-auto aspect-square max-h-[250px] h-auto!";
  return resolvedLayers.value
    ? `relative ${base} [&_[data-vis-single-container]]:absolute!`
    : base;
});

const centerStyle = computed(() =>
  resolvedCenterLabel.value
    ? {
        "--vis-donut-central-label-font-size": "1.5rem",
        "--vis-donut-central-label-font-weight": "600",
        "--vis-donut-central-sub-label-text-color": "var(--muted-foreground)",
      }
    : undefined
);

const currentConfig = computed(() => props.config);

const tooltipTemplate = componentToString(currentConfig, ChartTooltipContent, {
  hideLabel: true,
});

const tooltipTriggers = {
  [Donut.selectors.segment]: tooltipTemplate,
};
</script>
