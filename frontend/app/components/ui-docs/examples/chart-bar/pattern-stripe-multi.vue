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
  desktop: { label: "Desktop", color: "var(--chart-1)" },
  mobile: { label: "Mobile", color: "var(--chart-2)" },
};

const svgDefs = `
  <pattern id="chart5-diagonal-stripe-pattern" patternUnits="userSpaceOnUse" width="8" height="8">
    <rect width="8" height="8" fill="var(--color-desktop)" opacity="0.1" />
    <path d="M0,8 L8,0 M4,12 L12,4 M-4,4 L4,-4" stroke="var(--color-desktop)" stroke-width="1.5" opacity="0.6" />
    <path d="M2,10 L10,2 M6,14 L14,6 M-2,6 L6,-2" stroke="var(--color-desktop)" stroke-width="1" opacity="0.3" />
  </pattern>`;

const barFill = {
  desktop: "url(#chart5-diagonal-stripe-pattern)",
  mobile: "var(--chart-2)",
};
</script>

<template>
  <ClientOnly>
    <ChartCard title="User Acquisition" description="Quarterly user growth tracking">
      <template #trend>
        <TrendBadge direction="down" tone="destructive" :value="15" />
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
          :margin="{ top: 20, right: 12, bottom: 18, left: 12 }"
        />
      </div>
    </ChartCard>
  </ClientOnly>
</template>
