<script setup>
import { ChartRadar } from "@/components/ui/chart";
import ChartCard from "@/components/ui-docs/examples/_shared/ChartCard.vue";

const data = [
  { metric: "Speed", value: 88 },
  { metric: "Reliability", value: 94 },
  { metric: "Scalability", value: 72 },
  { metric: "Cost", value: 68 },
  { metric: "Security", value: 82 },
  { metric: "DX", value: 78 },
];

const config = {
  value: { label: "Score", color: "var(--chart-2)" },
};
</script>

<template>
  <ClientOnly>
    <ChartCard variant="center" size="xs" title="Infrastructure Score" description="Platform performance metrics">
      <ChartRadar
        :data="data"
        :config="config"
        data-key="value"
        category-key="metric"
        gradient-direction="diagonal"
        dot-variant="ring"
        :stroke-width="2.5"
        :fill-top="0.45"
        :fill-bottom="0.1"
        glow
        :glow-std-deviation="5"
        dots
      />
    </ChartCard>
  </ClientOnly>
</template>
