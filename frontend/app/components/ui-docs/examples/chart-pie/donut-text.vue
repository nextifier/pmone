<script setup>
import { ChartPie } from "@/components/ui/chart";

const data = [
  { browser: "chrome", visitors: 275, fill: "var(--color-chrome)" },
  { browser: "safari", visitors: 200, fill: "var(--color-safari)" },
  { browser: "firefox", visitors: 287, fill: "var(--color-firefox)" },
  { browser: "edge", visitors: 173, fill: "var(--color-edge)" },
  { browser: "other", visitors: 190, fill: "var(--color-other)" },
];

const config = {
  visitors: { label: "Visitors" },
  chrome: { label: "Chrome", color: "var(--chart-1)" },
  safari: { label: "Safari", color: "var(--chart-2)" },
  firefox: { label: "Firefox", color: "var(--chart-3)" },
  edge: { label: "Edge", color: "var(--chart-4)" },
  other: { label: "Other", color: "var(--chart-5)" },
};
</script>

<template>
  <ClientOnly>
    <div class="w-full">
      <ChartPie
        :data="data"
        :config="config"
        value-key="visitors"
        name-key="browser"
        :arc-width="30"
        center-sub-label="Visitors"
      />
    </div>
  </ClientOnly>
</template>
