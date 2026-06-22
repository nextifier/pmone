<script setup>
import { ChartAreaInteractive } from "@/components/ui/chart";

const chartData = Array.from({ length: 92 }, (_, i) => {
  const date = new Date("2024-04-01");
  date.setDate(date.getDate() + i);
  return {
    date,
    desktop: Math.round(180 + 140 * Math.sin(i / 6) + (i % 7) * 16),
    mobile: Math.round(160 + 110 * Math.cos(i / 5) + (i % 5) * 18),
  };
});

const config = {
  mobile: { label: "Mobile", color: "var(--chart-2)" },
  desktop: { label: "Desktop", color: "var(--chart-1)" },
};
</script>

<template>
  <ClientOnly>
    <div class="w-full">
      <ChartAreaInteractive
        :data="chartData"
        :config="config"
        :data-keys="['mobile', 'desktop']"
        title="Total visitors"
        description="Total for the selected range"
      />
    </div>
  </ClientOnly>
</template>
