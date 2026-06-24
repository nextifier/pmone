<script setup>
import { ref } from "vue";
import { ChartBarAnimated } from "@/components/ui/chart";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import TrendBadge from "@/components/ui-docs/examples/_shared/TrendBadge.vue";

const data = [
  { month: "January", desktop: 289 },
  { month: "February", desktop: 345 },
  { month: "March", desktop: 412 },
  { month: "April", desktop: 478 },
  { month: "May", desktop: 534 },
  { month: "June", desktop: 456 },
  { month: "July", desktop: 523 },
  { month: "August", desktop: 589 },
  { month: "September", desktop: 467 },
  { month: "October", desktop: 398 },
  { month: "November", desktop: 356 },
  { month: "December", desktop: 423 },
];

const config = {
  desktop: { label: "Desktop", color: "var(--chart-4)" },
};

const activeValue = ref(null);
</script>

<template>
  <ClientOnly>
    <Card class="w-full max-w-md">
      <CardHeader>
        <CardTitle class="flex items-center gap-2 tracking-tight">
          <span>Conversion Rates</span>
          <span class="ml-auto font-mono text-xl tracking-tighter tabular-nums">
            ${{ activeValue ?? 123 }}
          </span>
          <TrendBadge direction="up" tone="success" :value="5.2" />
        </CardTitle>
        <CardDescription class="tracking-tight">Real-time funnel conversion tracking</CardDescription>
      </CardHeader>
      <CardContent>
        <div class="[&_[data-slot=chart]]:h-[280px]!">
          <ChartBarAnimated
            :data="data"
            :config="config"
            x-key="month"
            value-key="desktop"
            @update:active="(v) => (activeValue = v)"
          />
        </div>
      </CardContent>
    </Card>
  </ClientOnly>
</template>
