<script setup>
import { ChartBar } from "@/components/ui/chart";
import ChartCard from "@/components/ui-docs/examples/_shared/ChartCard.vue";
import TrendBadge from "@/components/ui-docs/examples/_shared/TrendBadge.vue";

const data = [
  { month: "Jan", desktop: 300 },
  { month: "Feb", desktop: 600 },
  { month: "Mar", desktop: 500 },
  { month: "Apr", desktop: 600 },
  { month: "May", desktop: 400 },
  { month: "Jun", desktop: 780 },
  { month: "Jul", desktop: 390 },
  { month: "Aug", desktop: 920 },
  { month: "Sep", desktop: 640 },
  { month: "Oct", desktop: 530 },
  { month: "Nov", desktop: 800 },
  { month: "Dec", desktop: 270 },
];

const config = {
  desktop: { label: "Desktop", color: "var(--chart-1)" },
};

const svgDefs = `
  <linearGradient id="duotone-bar-pattern-desktop" x1="0" y1="0" x2="1" y2="0">
    <stop offset="50%" stop-color="var(--color-desktop)" stop-opacity="0.4" />
    <stop offset="50%" stop-color="var(--color-desktop)" />
  </linearGradient>`;
</script>

<template>
  <ClientOnly>
    <ChartCard title="Operational Efficiency" description="Supply chain performance metrics">
      <template #trend>
        <TrendBadge direction="up" tone="success" :value="12.5" />
      </template>
      <div class="[&_[data-slot=chart]]:h-[280px]!">
        <ChartBar
          :data="data"
          :config="config"
          data-key="desktop"
          x-key="month"
          :svg-defs="svgDefs"
          bar-fill="url(#duotone-bar-pattern-desktop)"
          :rounded-corners="6"
          :margin="{ top: 20, right: 12, bottom: 12, left: 12 }"
        />
      </div>
    </ChartCard>
  </ClientOnly>
</template>
