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
</script>

<template>
  <ClientOnly>
    <ChartCard title="Revenue Growth" description="Monthly revenue performance tracking">
      <template #trend>
        <TrendBadge direction="up" tone="success" :value="5.2" />
      </template>
      <div class="[&_[data-slot=chart]]:h-[280px]!">
        <ChartBar
          :data="data"
          :config="config"
          data-key="desktop"
          x-key="month"
          grid
          :rounded-corners="4"
          :margin="{ top: 12, right: 12, bottom: 18, left: 12 }"
        />
      </div>
    </ChartCard>
  </ClientOnly>
</template>
