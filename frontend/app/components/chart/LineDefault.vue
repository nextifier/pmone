<template>
  <ChartContainer
    :config="config"
    class="[&_text_&_tspan]:text-info! [&_.domain]:stroke-gray-200 dark:[&_.domain]:stroke-gray-800!"
  >
    <VisXYContainer
      :data="data"
      :margin="{ left: 4 }"
      :padding="{ top: 12, bottom: 12 }"
      :y-domain="[0, undefined]"
    >
      <VisLine
        :x="(d) => d.date"
        :y="(d) => d[dataKey]"
        :color="config[dataKey]?.color || 'var(--chart-1)'"
        :curve-type="CurveType.Natural"
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
        :tick-format="
          (d) => {
            return new Intl.NumberFormat('en-US', {
              notation: 'compact',
              maximumFractionDigits: 1,
            }).format(d);
          }
        "
      />
      <ChartTooltip />
      <ChartCrosshair
        :template="componentToString(config, ChartTooltipContent, { hideLabel: true })"
        :color="config[dataKey]?.color || 'var(--chart-1)'"
      />
    </VisXYContainer>
  </ChartContainer>
</template>

<script setup>
import { CurveType } from "@unovis/ts";

import {
  ChartContainer,
  ChartCrosshair,
  ChartTooltip,
  ChartTooltipContent,
  componentToString,
} from "@/components/ui/chart";
import { VisAxis, VisLine, VisXYContainer } from "@unovis/vue";

const props = defineProps({
  data: {
    type: Array,
    required: true,
  },
  config: {
    type: Object,
    required: true,
  },
  dataKey: {
    type: String,
    default: "value",
  },
});
</script>
