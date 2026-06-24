<script setup>
import { ChartBar } from "@/components/ui/chart";
import ChartCard from "@/components/ui-docs/examples/_shared/ChartCard.vue";
import TrendBadge from "@/components/ui-docs/examples/_shared/TrendBadge.vue";

const data = [
  { month: "January", desktop: 120, mobile: 80 },
  { month: "February", desktop: 250, mobile: 200 },
  { month: "March", desktop: 230, mobile: 120 },
  { month: "April", desktop: 70, mobile: 190 },
  { month: "May", desktop: 209, mobile: 130 },
  { month: "June", desktop: 210, mobile: 140 },
];

const config = {
  desktop: { label: "Desktop", color: "var(--chart-1)" },
  mobile: { label: "Mobile", color: "var(--chart-2)" },
};
</script>

<template>
  <ClientOnly>
    <ChartCard title="Market Share" description="Departmental performance comparison">
      <template #trend>
        <TrendBadge direction="up" tone="warning" :value="12" />
      </template>
      <div class="[&_[data-slot=chart]]:h-[280px]!">
        <ChartBar
          :data="data"
          :config="config"
          :data-keys="['desktop', 'mobile']"
          x-key="month"
          :rounded-corners="4"
          :x-tick-formatter="(v) => String(v).slice(0, 3)"
          :margin="{ top: 12, right: 12, bottom: 18, left: 12 }"
        />
      </div>
    </ChartCard>
  </ClientOnly>
</template>
