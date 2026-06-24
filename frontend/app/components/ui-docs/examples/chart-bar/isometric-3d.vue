<script setup>
import { ChartBar3D } from "@/components/ui/chart";
import ChartCard from "@/components/ui-docs/examples/_shared/ChartCard.vue";
import TrendBadge from "@/components/ui-docs/examples/_shared/TrendBadge.vue";

const data = [
  { month: "Jan", desktop: 340 },
  { month: "Feb", desktop: 870 },
  { month: "Mar", desktop: 510 },
  { month: "Apr", desktop: 620 },
  { month: "May", desktop: 450 },
  { month: "Jun", desktop: 780 },
  { month: "Jul", desktop: 390 },
  { month: "Aug", desktop: 920 },
  { month: "Sep", desktop: 640 },
  { month: "Oct", desktop: 530 },
  { month: "Nov", desktop: 800 },
  { month: "Dec", desktop: 270 },
];

const config = {
  desktop: { label: "Desktop", color: "var(--chart-5)" },
};
</script>

<template>
  <ClientOnly>
    <ChartCard title="Financial Forecast" description="Projected vs actual revenue growth">
      <template #trend>
        <TrendBadge direction="down" tone="destructive" :value="8.2" />
      </template>
      <ChartBar3D :data="data" :config="config" x-key="month" value-key="desktop" mode="solid" />
    </ChartCard>
  </ClientOnly>
</template>
