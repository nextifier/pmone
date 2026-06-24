<script setup>
import { ChartArea } from "@/components/ui/chart";
import ChartCard from "@/components/ui-docs/examples/_shared/ChartCard.vue";
import TrendBadge from "@/components/ui-docs/examples/_shared/TrendBadge.vue";

const data = [
  { month: "January", api: 1820, webhook: 1640 },
  { month: "February", api: 2340, webhook: 2160 },
  { month: "March", api: 1960, webhook: 1880 },
  { month: "April", api: 2780, webhook: 2540 },
  { month: "May", api: 2100, webhook: 1920 },
  { month: "June", api: 3120, webhook: 2880 },
  { month: "July", api: 2540, webhook: 2320 },
  { month: "August", api: 3480, webhook: 3160 },
  { month: "September", api: 2860, webhook: 2580 },
  { month: "October", api: 2420, webhook: 2140 },
  { month: "November", api: 3240, webhook: 2960 },
  { month: "December", api: 2680, webhook: 2440 },
];

const config = {
  api: { label: "API Calls", color: "var(--chart-1)" },
  webhook: { label: "Webhooks", color: "var(--chart-3)" },
};

const svgDefs = `
  <pattern id="chart18-crosshatch-api" x="0" y="0" width="8" height="8" patternUnits="userSpaceOnUse">
    <path d="M0,8 L8,0" stroke="var(--color-api)" stroke-width="0.8" opacity="0.4" />
    <path d="M0,0 L8,8" stroke="var(--color-api)" stroke-width="0.8" opacity="0.2" />
  </pattern>
  <pattern id="chart18-crosshatch-webhook" x="0" y="0" width="8" height="8" patternUnits="userSpaceOnUse">
    <path d="M0,8 L8,0" stroke="var(--color-webhook)" stroke-width="0.8" opacity="0.4" />
    <path d="M0,0 L8,8" stroke="var(--color-webhook)" stroke-width="0.8" opacity="0.2" />
  </pattern>`;

const areaFill = {
  api: "url(#chart18-crosshatch-api)",
  webhook: "url(#chart18-crosshatch-webhook)",
};
</script>

<template>
  <ClientOnly>
    <ChartCard
      title="Request Volume"
      description="API and webhook traffic over 12 months"
    >
      <template #trend>
        <TrendBadge direction="up" tone="success" :value="12.8" />
      </template>
      <div class="[&_[data-slot=chart]]:h-[280px]!">
        <ChartArea
          :data="data"
          :config="config"
          :data-keys="['webhook', 'api']"
          x-key="month"
          stacked
          curve-type="natural"
          :svg-defs="svgDefs"
          :area-fill="areaFill"
          :fill-opacity="0.5"
          :strokeWidthByKey="{ api: 1, webhook: 1 }"
          grid
          hide-y-axis
          :x-tick-formatter="(v) => String(v).slice(0, 3)"
          :margin="{ top: 20, right: 0, bottom: 0, left: 0 }"
        />
      </div>
    </ChartCard>
  </ClientOnly>
</template>
