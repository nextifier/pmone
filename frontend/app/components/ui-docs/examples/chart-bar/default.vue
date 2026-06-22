<script setup>
import { ChartBar } from "@/components/ui/chart";

const data = [
  { date: new Date("2024-01-01"), desktop: 186 },
  { date: new Date("2024-02-01"), desktop: 305 },
  { date: new Date("2024-03-01"), desktop: 237 },
  { date: new Date("2024-04-01"), desktop: 173 },
  { date: new Date("2024-05-01"), desktop: 209 },
  { date: new Date("2024-06-01"), desktop: 214 },
];

const config = {
  desktop: { label: "Desktop", color: "var(--chart-1)" },
};
</script>

<template>
  <ClientOnly>
    <div class="w-full [&_[data-slot=chart]]:h-[280px]!">
      <ChartBar :data="data" data-key="desktop" :config="config" />
    </div>
  </ClientOnly>
</template>
