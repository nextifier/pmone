<script setup>
import { ChartBar } from "@/components/ui/chart";
import ChartCard from "@/components/ui-docs/examples/_shared/ChartCard.vue";
import TrendBadge from "@/components/ui-docs/examples/_shared/TrendBadge.vue";

const data = [
  { month: "Jan", desktop: 300 },
  { month: "Feb", desktop: 550 },
  { month: "Mar", desktop: 400 },
  { month: "Apr", desktop: 630 },
  { month: "May", desktop: 460 },
  { month: "Jun", desktop: 780 },
  { month: "Jul", desktop: 390 },
  { month: "Aug", desktop: 925 },
  { month: "Sep", desktop: 645 },
  { month: "Oct", desktop: 530 },
  { month: "Nov", desktop: 700 },
  { month: "Dec", desktop: 270 },
];

const config = {
  desktop: { label: "Desktop", color: "var(--chart-2)" },
};

const svgDefs = `
  <pattern id="chart4-elegant-dotted-pattern" x="0" y="0" width="5" height="5" patternUnits="userSpaceOnUse">
    <rect width="5" height="5" fill="var(--color-desktop)" opacity="0.1" />
    <circle cx="5" cy="5" r="1.4" fill="var(--color-desktop)" opacity="0.6" />
  </pattern>`;
</script>

<template>
  <ClientOnly>
    <ChartCard title="Website Traffic" description="Monthly visitor behavior patterns">
      <template #trend>
        <TrendBadge direction="up" tone="success" :value="8" />
      </template>
      <div class="[&_[data-slot=chart]]:h-[280px]!">
        <ChartBar
          :data="data"
          :config="config"
          data-key="desktop"
          x-key="month"
          :svg-defs="svgDefs"
          bar-fill="url(#chart4-elegant-dotted-pattern)"
          bar-stroke="var(--color-desktop)"
          :bar-stroke-width="1"
          :rounded-corners="4"
          :margin="{ top: 20, right: 12, bottom: 12, left: 12 }"
        />
      </div>
    </ChartCard>
  </ClientOnly>
</template>
