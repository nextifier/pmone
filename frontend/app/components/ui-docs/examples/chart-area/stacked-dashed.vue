<script setup>
import { ChartArea } from "@/components/ui/chart";
import ChartCard from "@/components/ui-docs/examples/_shared/ChartCard.vue";
import TrendBadge from "@/components/ui-docs/examples/_shared/TrendBadge.vue";

const data = [
  { month: "January", organic: 1200, paid: 580, referral: 320 },
  { month: "February", organic: 1450, paid: 620, referral: 380 },
  { month: "March", organic: 1380, paid: 540, referral: 420 },
  { month: "April", organic: 1650, paid: 710, referral: 460 },
  { month: "May", organic: 1520, paid: 680, referral: 390 },
  { month: "June", organic: 1800, paid: 750, referral: 510 },
];

const config = {
  organic: { label: "Organic", color: "var(--chart-1)" },
  paid: { label: "Paid", color: "var(--chart-2)" },
  referral: { label: "Referral", color: "var(--chart-3)" },
};

const svgDefs = `
  <linearGradient id="chart14-organic" x1="0" y1="0" x2="0" y2="1">
    <stop offset="5%" stop-color="var(--color-organic)" stop-opacity="0.5" />
    <stop offset="95%" stop-color="var(--color-organic)" stop-opacity="0.1" />
  </linearGradient>
  <linearGradient id="chart14-paid" x1="0" y1="0" x2="0" y2="1">
    <stop offset="5%" stop-color="var(--color-paid)" stop-opacity="0.5" />
    <stop offset="95%" stop-color="var(--color-paid)" stop-opacity="0.1" />
  </linearGradient>
  <linearGradient id="chart14-referral" x1="0" y1="0" x2="0" y2="1">
    <stop offset="5%" stop-color="var(--color-referral)" stop-opacity="0.5" />
    <stop offset="95%" stop-color="var(--color-referral)" stop-opacity="0.1" />
  </linearGradient>`;
</script>

<template>
  <ClientOnly>
    <ChartCard title="Traffic Sources" description="Visitor acquisition by channel">
      <template #trend>
        <TrendBadge direction="up" tone="success" :value="18.3" />
      </template>
      <div class="[&_[data-slot=chart]]:h-[280px]!">
        <ChartArea
          :data="data"
          :config="config"
          :data-keys="['organic', 'paid', 'referral']"
          x-key="month"
          stacked
          :svg-defs="svgDefs"
          :area-fill="{
            organic: 'url(#chart14-organic)',
            paid: 'url(#chart14-paid)',
            referral: 'url(#chart14-referral)',
          }"
          :fill-opacity="0.4"
          :dashed-keys="['paid', 'referral']"
          :strokeWidthByKey="{ organic: 2, paid: 0.8, referral: 0.8 }"
          grid
          hide-y-axis
          :x-tick-formatter="(value) => String(value).slice(0, 3)"
          :margin="{ top: 20, right: 0, bottom: 0, left: 0 }"
        />
      </div>
    </ChartCard>
  </ClientOnly>
</template>
