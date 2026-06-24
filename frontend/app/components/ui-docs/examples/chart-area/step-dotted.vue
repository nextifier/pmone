<script setup>
import { ChartArea } from "@/components/ui/chart";
import ChartCard from "@/components/ui-docs/examples/_shared/ChartCard.vue";
import TrendBadge from "@/components/ui-docs/examples/_shared/TrendBadge.vue";

const data = [
  { month: "January", connections: 48 },
  { month: "February", connections: 62 },
  { month: "March", connections: 62 },
  { month: "April", connections: 85 },
  { month: "May", connections: 85 },
  { month: "June", connections: 110 },
  { month: "July", connections: 110 },
  { month: "August", connections: 135 },
];

const config = {
  connections: { label: "Connections", color: "var(--chart-2)" },
};

const svgDefs = `
  <pattern id="chart15-dot-pattern" patternUnits="userSpaceOnUse" width="5" height="5">
    <rect width="5" height="5" fill="var(--color-connections)" opacity="0.08" />
    <circle cx="2.5" cy="2.5" r="1" fill="var(--color-connections)" opacity="0.5" />
  </pattern>
  <linearGradient id="chart15-stroke-grad" x1="0" y1="0" x2="1" y2="0">
    <stop offset="0%" stop-color="var(--color-connections)" stop-opacity="0.4" />
    <stop offset="100%" stop-color="var(--color-connections)" stop-opacity="1" />
  </linearGradient>`;
</script>

<template>
  <ClientOnly>
    <ChartCard title="Active Connections" description="Server connection pool over time">
      <template #trend>
        <TrendBadge direction="up" tone="success" :value="257" />
      </template>
      <div class="[&_[data-slot=chart]]:h-[280px]!">
        <ChartArea
          :data="data"
          :config="config"
          data-key="connections"
          x-key="month"
          curve-type="stepAfter"
          :svg-defs="svgDefs"
          area-fill="url(#chart15-dot-pattern)"
          :strokeWidthByKey="{ connections: 2 }"
          :x-tick-formatter="(value) => value.slice(0, 3)"
          grid
          hide-y-axis
          :margin="{ top: 20, right: 2, bottom: 0, left: 2 }"
        />
      </div>
    </ChartCard>
  </ClientOnly>
</template>
