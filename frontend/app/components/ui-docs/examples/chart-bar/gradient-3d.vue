<script setup>
import { ChartBar3D } from "@/components/ui/chart";
import ChartCard from "@/components/ui-docs/examples/_shared/ChartCard.vue";
import TrendBadge from "@/components/ui-docs/examples/_shared/TrendBadge.vue";

const data = [
  { month: "Jan", desktop: 340 },
  { month: "Feb", desktop: 600 },
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
  desktop: { label: "Desktop", color: "var(--chart-1)" },
};
</script>

<template>
  <ClientOnly>
    <ChartCard title="Regional Performance" description="Global sales distribution by region">
      <template #trend>
        <TrendBadge direction="up" tone="success" :value="15.7" />
      </template>
      <ChartBar3D :data="data" :config="config" x-key="month" value-key="desktop" mode="gradient" />
    </ChartCard>
  </ClientOnly>
</template>
