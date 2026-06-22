<script setup>
import { ChartLine } from "@/components/ui/chart";

const data = [
  { date: new Date("2024-01-01"), visitors: 1200 },
  { date: new Date("2024-02-01"), visitors: 1800 },
  { date: new Date("2024-03-01"), visitors: 1500 },
  { date: new Date("2024-04-01"), visitors: 2400 },
  { date: new Date("2024-05-01"), visitors: 2100 },
  { date: new Date("2024-06-01"), visitors: 2900 },
];

const comparison = [
  { date: new Date("2024-01-01"), visitors: 900 },
  { date: new Date("2024-02-01"), visitors: 1100 },
  { date: new Date("2024-03-01"), visitors: 1400 },
  { date: new Date("2024-04-01"), visitors: 1700 },
  { date: new Date("2024-05-01"), visitors: 1600 },
  { date: new Date("2024-06-01"), visitors: 2200 },
];

const config = {
  visitors: { label: "Visitors", color: "var(--chart-1)" },
};
</script>

<template>
  <ClientOnly>
    <div class="w-full [&_[data-slot=chart]]:h-[280px]!">
      <ChartLine
        :data="data"
        :comparison-data="comparison"
        data-key="visitors"
        :config="config"
        gradient
        comparison-label="Previous period"
      />
    </div>
  </ClientOnly>
</template>
