<script setup>
import { ChartBar } from "@/components/ui/chart";

const data = [
  { date: new Date("2024-01-01"), desktop: 186, mobile: 80 },
  { date: new Date("2024-02-01"), desktop: 305, mobile: 200 },
  { date: new Date("2024-03-01"), desktop: 237, mobile: 120 },
  { date: new Date("2024-04-01"), desktop: 173, mobile: 190 },
  { date: new Date("2024-05-01"), desktop: 209, mobile: 130 },
  { date: new Date("2024-06-01"), desktop: 214, mobile: 140 },
];

const config = {
  desktop: { label: "Desktop", color: "var(--chart-1)" },
  mobile: { label: "Mobile", color: "var(--chart-2)" },
};
</script>

<template>
  <ClientOnly>
    <div class="w-full [&_[data-slot=chart]]:h-[280px]!">
      <ChartBar :data="data" :data-keys="['desktop', 'mobile']" :config="config" />
    </div>
  </ClientOnly>
</template>
