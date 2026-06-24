<script setup>
import { ChartArea } from "@/components/ui/chart";
import ChartCard from "@/components/ui-docs/examples/_shared/ChartCard.vue";
import TrendBadge from "@/components/ui-docs/examples/_shared/TrendBadge.vue";

const data = [
  { week: "W1", signups: 64 },
  { week: "W2", signups: 78 },
  { week: "W3", signups: 52 },
  { week: "W4", signups: 92 },
  { week: "W5", signups: 85 },
  { week: "W6", signups: 110 },
  { week: "W7", signups: 98 },
  { week: "W8", signups: 125 },
];

const config = {
  signups: { label: "Signups", color: "var(--chart-3)" },
};

const svgDefs = `
  <linearGradient id="chart16-fill" x1="0" y1="0" x2="0" y2="1">
    <stop offset="5%" stop-color="var(--color-signups)" stop-opacity="0.35" />
    <stop offset="95%" stop-color="var(--color-signups)" stop-opacity="0" />
  </linearGradient>
  <filter id="chart16-dot-glow" x="-50%" y="-50%" width="200%" height="200%">
    <feGaussianBlur stdDeviation="3" result="blur" />
    <feComposite in="SourceGraphic" in2="blur" operator="over" />
  </filter>
  <filter id="chart16-line-glow" x="-10%" y="-20%" width="120%" height="140%">
    <feGaussianBlur stdDeviation="8" result="blur" />
    <feComposite in="SourceGraphic" in2="blur" operator="over" />
  </filter>`;
</script>

<template>
  <ClientOnly>
    <ChartCard title="New Signups" description="Weekly user registration trends">
      <template #trend>
        <TrendBadge direction="up" tone="success" :value="144" />
      </template>
      <div class="[&_[data-slot=chart]]:h-[280px]!">
        <ChartArea
          :data="data"
          :config="config"
          data-key="signups"
          x-key="week"
          :svg-defs="svgDefs"
          area-fill="url(#chart16-fill)"
          line-filter="url(#chart16-line-glow)"
          :strokeWidthByKey="{ signups: 2 }"
          dots
          dot-filter="url(#chart16-dot-glow)"
          :dot-size="8"
          grid
          hide-y-axis
          :num-x-ticks="8"
          :margin="{ top: 20, right: 8, bottom: 18, left: 8 }"
        />
      </div>
    </ChartCard>
  </ClientOnly>
</template>
