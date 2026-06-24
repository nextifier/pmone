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
  desktop: { label: "Desktop", color: "var(--chart-1)" },
};

const svgDefs = `
  <pattern id="chart3-diagonal-stripe-pattern" patternUnits="userSpaceOnUse" width="8" height="8">
    <rect width="8" height="8" fill="var(--color-desktop)" opacity="0.1" />
    <path d="M0,8 L8,0 M4,12 L12,4 M-4,4 L4,-4" stroke="var(--color-desktop)" stroke-width="1.5" opacity="0.6" />
    <path d="M2,10 L10,2 M6,14 L14,6 M-2,6 L6,-2" stroke="var(--color-desktop)" stroke-width="1" opacity="0.3" />
  </pattern>`;
</script>

<template>
  <ClientOnly>
    <ChartCard title="Product Sales" description="Annual sales trend visualization">
      <template #trend>
        <TrendBadge direction="up" tone="destructive" :value="4.3" />
      </template>
      <div class="[&_[data-slot=chart]]:h-[280px]!">
        <ChartBar
          :data="data"
          :config="config"
          data-key="desktop"
          x-key="month"
          :svg-defs="svgDefs"
          bar-fill="url(#chart3-diagonal-stripe-pattern)"
          bar-stroke="var(--color-desktop)"
          :bar-stroke-width="1"
          :rounded-corners="4"
          :margin="{ top: 20, right: 12, bottom: 18, left: 12 }"
        />
      </div>
    </ChartCard>
  </ClientOnly>
</template>
