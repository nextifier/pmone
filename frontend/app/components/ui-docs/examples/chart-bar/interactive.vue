<script setup>
import { ChartBarInteractive } from "@/components/ui/chart";

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
  desktop: { label: "Desktop", color: "var(--chart-1)" },
  mobile: { label: "Mobile", color: "var(--chart-2)" },
};
</script>

<template>
  <ClientOnly>
    <div class="w-full">
      <ChartBarInteractive
        :data="chartData"
        :config="config"
        :data-keys="['desktop', 'mobile']"
        title="Page views"
        description="Total for the last 3 months"
      />
    </div>
  </ClientOnly>
</template>
