<script setup>
import { ChartBar } from "@/components/ui/chart";
import ChartCard from "@/components/ui-docs/examples/_shared/ChartCard.vue";
import TrendBadge from "@/components/ui-docs/examples/_shared/TrendBadge.vue";

const data = [
  { month: "Jan", desktop: 342, mobile: 245 },
  { month: "Feb", desktop: 876, mobile: 654 },
  { month: "Mar", desktop: 512, mobile: 389 },
  { month: "Apr", desktop: 629, mobile: 521 },
  { month: "May", desktop: 458, mobile: 367 },
  { month: "Jun", desktop: 781, mobile: 598 },
];

const config = {
  desktop: { label: "Desktop", color: "var(--chart-1)" },
  mobile: { label: "Mobile", color: "var(--chart-2)" },
};
</script>

<template>
  <ClientOnly>
    <ChartCard title="Inventory Levels" description="Stock availability across warehouses">
      <template #trend>
        <TrendBadge direction="down" tone="destructive" :value="3.2" />
      </template>
      <div class="[&_[data-slot=chart]]:h-[280px]!">
        <ChartBar
          :data="data"
          :config="config"
          :data-keys="['desktop', 'mobile']"
          x-key="month"
          horizontal
          :rounded-corners="2"
          :margin="{ top: 8, right: 12, bottom: 8, left: 60 }"
        />
      </div>
    </ChartCard>
  </ClientOnly>
</template>
