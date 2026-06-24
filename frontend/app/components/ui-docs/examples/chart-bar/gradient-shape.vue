<script setup>
import { ChartBar } from "@/components/ui/chart";
import ChartCard from "@/components/ui-docs/examples/_shared/ChartCard.vue";
import TrendBadge from "@/components/ui-docs/examples/_shared/TrendBadge.vue";

const data = [
  { month: "Jan", desktop: 120 },
  { month: "Feb", desktop: 250 },
  { month: "Mar", desktop: 200 },
  { month: "Apr", desktop: 170 },
  { month: "May", desktop: 209 },
  { month: "Jun", desktop: 210 },
  { month: "Jul", desktop: 150 },
  { month: "Aug", desktop: 230 },
  { month: "Sep", desktop: 180 },
  { month: "Oct", desktop: 190 },
  { month: "Nov", desktop: 200 },
  { month: "Dec", desktop: 120 },
];

const config = {
  desktop: { label: "Desktop", color: "var(--chart-3)" },
};

const svgDefs = `
  <linearGradient id="gradient-bar-pattern-desktop" x1="0" y1="0" x2="0" y2="1">
    <stop offset="0%" stop-color="var(--color-desktop)" stop-opacity="0.7" />
    <stop offset="50%" stop-color="var(--color-desktop)" stop-opacity="0.4" />
    <stop offset="100%" stop-color="var(--color-desktop)" stop-opacity="0.2" />
  </linearGradient>`;
</script>

<template>
  <ClientOnly>
    <ChartCard
      title="Engagement Metrics"
      description="User interaction and click-through rates"
    >
      <template #trend>
        <TrendBadge direction="up" tone="success" :value="2.4" />
      </template>
      <div class="[&_[data-slot=chart]]:h-[280px]!">
        <ChartBar
          :data="data"
          :config="config"
          data-key="desktop"
          x-key="month"
          :svg-defs="svgDefs"
          bar-fill="url(#gradient-bar-pattern-desktop)"
          :rounded-corners="6"
          :margin="{ top: 20, right: 12, bottom: 12, left: 12 }"
        />
      </div>
    </ChartCard>
  </ClientOnly>
</template>
