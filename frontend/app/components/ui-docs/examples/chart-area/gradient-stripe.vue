<script setup>
import { ChartArea } from "@/components/ui/chart";
import ChartCard from "@/components/ui-docs/examples/_shared/ChartCard.vue";
import TrendBadge from "@/components/ui-docs/examples/_shared/TrendBadge.vue";

const data = [
  { month: "January", visitors: 2400 },
  { month: "February", visitors: 2850 },
  { month: "March", visitors: 2600 },
  { month: "April", visitors: 3100 },
  { month: "May", visitors: 2900 },
  { month: "June", visitors: 3400 },
];

const config = {
  visitors: { label: "Visitors", color: "var(--chart-1)" },
};

const svgDefs = `
  <linearGradient id="chart13-gradient" x1="0" y1="0" x2="0" y2="1">
    <stop offset="5%" stop-color="var(--color-visitors)" stop-opacity="0.5" />
    <stop offset="95%" stop-color="var(--color-visitors)" stop-opacity="0.05" />
  </linearGradient>
  <pattern id="chart13-stripe" patternUnits="userSpaceOnUse" width="6" height="6">
    <path d="M0,6 L6,0" stroke="var(--color-visitors)" stroke-width="0.8" opacity="0.15" />
  </pattern>`;
</script>

<template>
  <ClientOnly>
    <ChartCard title="Website Traffic" description="Monthly unique visitor trends">
      <template #trend>
        <TrendBadge direction="up" tone="success" :value="24.5" />
      </template>
      <div class="[&_[data-slot=chart]]:h-[280px]!">
        <ChartArea
          :data="data"
          :config="config"
          data-key="visitors"
          x-key="month"
          curve-type="natural"
          :svg-defs="svgDefs"
          area-fill="url(#chart13-gradient)"
          :strokeWidthByKey="{ visitors: 2 }"
          :x-tick-formatter="(value) => String(value).slice(0, 3)"
          grid
          hide-y-axis
          :margin="{ top: 20, right: 0, bottom: 0, left: 0 }"
        />
      </div>
    </ChartCard>
  </ClientOnly>
</template>
