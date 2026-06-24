<script setup>
import { ChartPie } from "@/components/ui/chart";
import ChartCard from "@/components/ui-docs/examples/_shared/ChartCard.vue";

const data = [
  { source: "direct", visits: 4200, fill: "url(#gradient-direct)" },
  { source: "search", visits: 3600, fill: "url(#gradient-search)" },
  { source: "social", visits: 2800, fill: "url(#gradient-social)" },
  { source: "email", visits: 1900, fill: "url(#gradient-email)" },
  { source: "referral", visits: 1400, fill: "url(#gradient-referral)" },
];

const config = {
  visits: { label: "Visits" },
  direct: { label: "Direct", color: "var(--chart-1)" },
  search: { label: "Search", color: "var(--chart-2)" },
  social: { label: "Social", color: "var(--chart-3)" },
  email: { label: "Email", color: "var(--chart-4)" },
  referral: { label: "Referral", color: "var(--chart-5)" },
};

const total = data.reduce((s, d) => s + d.visits, 0);
const centerLabel = `${(total / 1000).toFixed(1)}k`;

const svgDefs = `
  <filter id="chart19-3d" x="-20%" y="-20%" width="140%" height="140%">
    <feDropShadow dx="0" dy="8" stdDeviation="5" flood-opacity="0.2" />
  </filter>
  <linearGradient id="gradient-direct" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="var(--chart-1)" stop-opacity="1" /><stop offset="100%" stop-color="var(--chart-1)" stop-opacity="0.8" /></linearGradient>
  <linearGradient id="gradient-search" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="var(--chart-2)" stop-opacity="1" /><stop offset="100%" stop-color="var(--chart-2)" stop-opacity="0.8" /></linearGradient>
  <linearGradient id="gradient-social" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="var(--chart-3)" stop-opacity="1" /><stop offset="100%" stop-color="var(--chart-3)" stop-opacity="0.8" /></linearGradient>
  <linearGradient id="gradient-email" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="var(--chart-4)" stop-opacity="1" /><stop offset="100%" stop-color="var(--chart-4)" stop-opacity="0.8" /></linearGradient>
  <linearGradient id="gradient-referral" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="var(--chart-5)" stop-opacity="1" /><stop offset="100%" stop-color="var(--chart-5)" stop-opacity="0.8" /></linearGradient>`;

const padAngle = (2 * Math.PI) / 180;
</script>

<template>
  <ClientOnly>
    <ChartCard variant="center" size="xs" title="Traffic Sources" description="Where your visitors come from">
      <ChartPie
        :data="data"
        :config="config"
        value-key="visits"
        name-key="source"
        :radius="100"
        :arc-width="30"
        :corner-radius="8"
        :pad-angle="padAngle"
        :svg-defs="svgDefs"
        donut-filter="url(#chart19-3d)"
        :center-label="centerLabel"
        center-sub-label="Total Visits"
      />
    </ChartCard>
  </ClientOnly>
</template>
