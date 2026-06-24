<script setup>
import { ChartPie } from "@/components/ui/chart";
import ChartCard from "@/components/ui-docs/examples/_shared/ChartCard.vue";

const data = [
  { priority: "critical", bugs: 14, fill: "url(#chart22-critical-pattern)" },
  { priority: "high", bugs: 28, fill: "var(--color-high)" },
  { priority: "medium", bugs: 42, fill: "url(#chart22-medium-pattern)" },
  { priority: "low", bugs: 36, fill: "var(--color-low)" },
];

const config = {
  bugs: { label: "Bugs" },
  critical: { label: "Critical", color: "var(--destructive)" },
  high: { label: "High", color: "var(--chart-4)" },
  medium: { label: "Medium", color: "var(--chart-3)" },
  low: { label: "Low", color: "var(--chart-5)" },
};

const svgDefs = `
  <pattern id="chart22-critical-pattern" patternUnits="userSpaceOnUse" width="6" height="6">
    <rect width="6" height="6" fill="var(--destructive)" opacity="0.3" />
    <path d="M0,6 L6,0 M-2,2 L2,-2 M4,8 L8,4" stroke="var(--destructive)" stroke-width="1.5" opacity="0.9" />
  </pattern>
  <pattern id="chart22-medium-pattern" patternUnits="userSpaceOnUse" width="5" height="5">
    <rect width="5" height="5" fill="var(--chart-3)" opacity="0.2" />
    <circle cx="2.5" cy="2.5" r="1.2" fill="var(--chart-3)" opacity="0.7" />
  </pattern>`;

const padAngle = (2 * Math.PI) / 180;
</script>

<template>
  <ClientOnly>
    <ChartCard variant="center" size="xs" title="Bug Priority" description="Open issues by severity level">
      <ChartPie
        :data="data"
        :config="config"
        value-key="bugs"
        name-key="priority"
        :radius="100"
        :arc-width="60"
        :corner-radius="4"
        :pad-angle="padAngle"
        :svg-defs="svgDefs"
        legend
      />
    </ChartCard>
  </ClientOnly>
</template>
