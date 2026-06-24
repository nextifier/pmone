<script setup>
import { ChartComposed } from "@/components/ui/chart";
import ChartCard from "@/components/ui-docs/examples/_shared/ChartCard.vue";

const data = [
  { month: "January", forecast: 2600, forecastArea: 2600 },
  { month: "February", forecast: 4200, forecastArea: 4200 },
  { month: "March", forecast: 2400, forecastArea: 2400 },
  { month: "April", forecast: 5000, forecastArea: 5000 },
  { month: "May", forecast: 2800, forecastArea: 2800 },
  { month: "June", forecast: 5800, forecastArea: 5800 },
  { month: "July", forecast: 3200, forecastArea: 3200 },
  { month: "August", forecast: 6200, forecastArea: 6200 },
  { month: "September", forecast: 3800, forecastArea: 3800 },
];

const config = {
  forecast: { label: "Forecast", color: "var(--chart-4)" },
};

const svgDefs = `
  <pattern id="chart17-forecast-stripe" patternUnits="userSpaceOnUse" width="6" height="6">
    <rect width="6" height="6" fill="var(--color-forecast)" opacity="0.04" />
    <path d="M0,6 L6,0" stroke="var(--color-forecast)" stroke-width="0.8" opacity="0.15" />
  </pattern>`;
</script>

<template>
  <ClientOnly>
    <ChartCard title="Sales Forecast" description="Projected sales performance with trends">
      <div class="[&_[data-slot=chart]]:h-[280px]!">
        <ChartComposed
          :data="data"
          :config="config"
          x-key="month"
          area-key="forecastArea"
          line-key="forecast"
          area-fill="url(#chart17-forecast-stripe)"
          :svg-defs="svgDefs"
          :x-tick-formatter="(v) => String(v).slice(0, 3)"
          legend
          :margin="{ top: 20, right: 8, bottom: 18, left: 26 }"
        />
      </div>
    </ChartCard>
  </ClientOnly>
</template>
