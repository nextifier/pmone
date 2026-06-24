<script setup>
import { ChartBar } from "@/components/ui/chart";
import ChartCard from "@/components/ui-docs/examples/_shared/ChartCard.vue";
import TrendBadge from "@/components/ui-docs/examples/_shared/TrendBadge.vue";

const data = [
  { month: "Jan", desktop: 340, mobile: 180 },
  { month: "Feb", desktop: 870, mobile: 420 },
  { month: "Mar", desktop: 510, mobile: 280 },
  { month: "Apr", desktop: 620, mobile: 350 },
  { month: "May", desktop: 450, mobile: 240 },
  { month: "Jun", desktop: 780, mobile: 390 },
];

const config = {
  desktop: { label: "Desktop", color: "var(--chart-2)" },
  mobile: { label: "Mobile", color: "var(--chart-2)" },
};

const svgDefs = `
  <pattern id="chart6-elegant-dotted-pattern" x="0" y="0" width="5" height="5" patternUnits="userSpaceOnUse">
    <rect width="5" height="5" fill="var(--color-desktop)" opacity="0.1" />
    <circle cx="5" cy="5" r="1.4" fill="var(--color-desktop)" opacity="1" />
  </pattern>`;

const barFill = {
  desktop: "url(#chart6-elegant-dotted-pattern)",
  mobile: "var(--color-mobile)",
};
</script>

<template>
  <ClientOnly>
    <ChartCard title="Customer Retention" description="Customer loyalty across segments">
      <template #trend>
        <TrendBadge direction="up" tone="success" :value="18.4" />
      </template>
      <div class="[&_[data-slot=chart]]:h-[280px]!">
        <ChartBar
          :data="data"
          :config="config"
          :data-keys="['desktop', 'mobile']"
          x-key="month"
          :svg-defs="svgDefs"
          :bar-fill="barFill"
          :rounded-corners="4"
          :margin="{ top: 20, right: 12, bottom: 12, left: 12 }"
        />
      </div>
    </ChartCard>
  </ClientOnly>
</template>
