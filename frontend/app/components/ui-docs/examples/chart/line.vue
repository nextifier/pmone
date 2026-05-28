<script setup>
import { ChartLineDefault } from "@/components/ui/chart";

const data = [
  { date: "2024-01-01", revenue: 1200 },
  { date: "2024-02-01", revenue: 1800 },
  { date: "2024-03-01", revenue: 1500 },
  { date: "2024-04-01", revenue: 2400 },
  { date: "2024-05-01", revenue: 2100 },
  { date: "2024-06-01", revenue: 2900 },
];

const config = {
  revenue: { label: "Revenue", color: "var(--chart-1)" },
};
</script>

<template>
  <ClientOnly>
    <div class="h-64 w-full">
      <ChartLineDefault :data="data" data-key="revenue" :config="config" />
    </div>
  </ClientOnly>
</template>
