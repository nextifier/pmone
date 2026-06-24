<script setup>
import { ChartRadialBar } from "@/components/ui/chart";
import ChartCard from "@/components/ui-docs/examples/_shared/ChartCard.vue";

const data = [
  { name: "mobile", score: 58 },
  { name: "desktop", score: 76 },
  { name: "api", score: 92 },
];

const config = {
  score: { label: "Score" },
  mobile: { label: "Mobile", color: "var(--chart-4)" },
  desktop: { label: "Desktop", color: "var(--chart-2)" },
  api: { label: "API", color: "var(--chart-1)" },
};
</script>

<template>
  <ClientOnly>
    <ChartCard variant="center" size="xs" title="Lighthouse Scores" description="Performance audit by platform">
      <ChartRadialBar :data="data" :config="config" value-key="score" name-key="name" />
    </ChartCard>
  </ClientOnly>
</template>
