<template>
  <div class="mx-auto max-w-7xl space-y-8 p-6">
    <div class="flex items-center gap-x-2.5">
      <Icon name="hugeicons:chart" class="size-5 sm:size-6" />
      <h1 class="text-2xl font-bold">Chart Examples</h1>
    </div>

    <p class="text-muted-foreground">
      Comprehensive examples of all available chart types using shadcn-vue and Unovis.
    </p>

    <!-- Area Chart Example -->
    <div class="space-y-3">
      <div>
        <h2 class="text-xl font-semibold">Area Chart</h2>
        <p class="text-muted-foreground text-sm">Monthly revenue comparison with predictions</p>
      </div>
      <div class="bg-card rounded-lg border p-6">
        <AreaChart
          :data="areaChartData"
          index="month"
          :categories="['revenue', 'predicted']"
          :show-gradient="true"
        />
      </div>
    </div>

    <!-- Line Chart Example -->
    <div class="space-y-3">
      <div>
        <h2 class="text-xl font-semibold">Line Chart</h2>
        <p class="text-muted-foreground text-sm">User engagement metrics over time</p>
      </div>
      <div class="bg-card rounded-lg border p-6">
        <LineChart
          :data="lineChartData"
          index="date"
          :categories="['pageViews', 'sessions', 'users']"
        />
      </div>
    </div>

    <!-- Bar Chart - Grouped Example -->
    <div class="space-y-3">
      <div>
        <h2 class="text-xl font-semibold">Bar Chart (Grouped)</h2>
        <p class="text-muted-foreground text-sm">Product sales comparison by quarter</p>
      </div>
      <div class="bg-card rounded-lg border p-6">
        <BarChart
          :data="barChartData"
          index="quarter"
          :categories="['productA', 'productB', 'productC']"
          type="grouped"
          :rounded-corners="4"
        />
      </div>
    </div>

    <!-- Bar Chart - Stacked Example -->
    <div class="space-y-3">
      <div>
        <h2 class="text-xl font-semibold">Bar Chart (Stacked)</h2>
        <p class="text-muted-foreground text-sm">Traffic sources by month</p>
      </div>
      <div class="bg-card rounded-lg border p-6">
        <BarChart
          :data="stackedBarChartData"
          index="month"
          :categories="['organic', 'paid', 'direct', 'referral']"
          type="stacked"
          :rounded-corners="4"
        />
      </div>
    </div>

    <!-- Donut Chart Example -->
    <div class="space-y-3">
      <div>
        <h2 class="text-xl font-semibold">Donut Chart</h2>
        <p class="text-muted-foreground text-sm">Browser usage distribution</p>
      </div>
      <div class="bg-card rounded-lg border p-6">
        <DonutChart
          :data="donutChartData"
          index="browser"
          category="users"
          :value-formatter="(value) => `${value.toLocaleString()} users`"
        />
      </div>
    </div>

    <!-- Pie Chart Example -->
    <div class="space-y-3">
      <div>
        <h2 class="text-xl font-semibold">Pie Chart</h2>
        <p class="text-muted-foreground text-sm">Device type distribution</p>
      </div>
      <div class="bg-card rounded-lg border p-6">
        <DonutChart
          :data="pieChartData"
          index="device"
          category="percentage"
          type="pie"
          :value-formatter="(value) => `${value}%`"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { AreaChart } from "@/components/ui/chart-area";
import { BarChart } from "@/components/ui/chart-bar";
import { DonutChart } from "@/components/ui/chart-donut";
import { LineChart } from "@/components/ui/chart-line";

usePageMeta("", {
  title: "Hello",
});

// Area Chart Data
const areaChartData = [
  { month: "Jan", revenue: 45000, predicted: 42000 },
  { month: "Feb", revenue: 52000, predicted: 48000 },
  { month: "Mar", revenue: 48000, predicted: 51000 },
  { month: "Apr", revenue: 61000, predicted: 58000 },
  { month: "May", revenue: 58000, predicted: 62000 },
  { month: "Jun", revenue: 70000, predicted: 68000 },
  { month: "Jul", revenue: 75000, predicted: 73000 },
  { month: "Aug", revenue: 72000, predicted: 76000 },
  { month: "Sep", revenue: 68000, predicted: 71000 },
  { month: "Oct", revenue: 79000, predicted: 78000 },
  { month: "Nov", revenue: 85000, predicted: 82000 },
  { month: "Dec", revenue: 92000, predicted: 88000 },
];

// Line Chart Data
const lineChartData = [
  { date: "Mon", pageViews: 3200, sessions: 1800, users: 1200 },
  { date: "Tue", pageViews: 3800, sessions: 2100, users: 1450 },
  { date: "Wed", pageViews: 4200, sessions: 2400, users: 1680 },
  { date: "Thu", pageViews: 4600, sessions: 2700, users: 1920 },
  { date: "Fri", pageViews: 5100, sessions: 3000, users: 2150 },
  { date: "Sat", pageViews: 3600, sessions: 2200, users: 1500 },
  { date: "Sun", pageViews: 3000, sessions: 1900, users: 1300 },
];

// Bar Chart Data - Grouped
const barChartData = [
  { quarter: "Q1", productA: 15000, productB: 12000, productC: 8000 },
  { quarter: "Q2", productA: 18000, productB: 14000, productC: 9500 },
  { quarter: "Q3", productA: 22000, productB: 16000, productC: 11000 },
  { quarter: "Q4", productA: 25000, productB: 19000, productC: 13500 },
];

// Bar Chart Data - Stacked
const stackedBarChartData = [
  { month: "Jan", organic: 2400, paid: 1800, direct: 900, referral: 600 },
  { month: "Feb", organic: 2600, paid: 2000, direct: 950, referral: 650 },
  { month: "Mar", organic: 2900, paid: 2200, direct: 1000, referral: 700 },
  { month: "Apr", organic: 3200, paid: 2400, direct: 1100, referral: 750 },
  { month: "May", organic: 3500, paid: 2600, direct: 1200, referral: 800 },
  { month: "Jun", organic: 3800, paid: 2800, direct: 1300, referral: 850 },
];

// Donut Chart Data
const donutChartData = [
  { browser: "Chrome", users: 45200 },
  { browser: "Safari", users: 28400 },
  { browser: "Firefox", users: 15800 },
  { browser: "Edge", users: 12600 },
  { browser: "Other", users: 8900 },
];

// Pie Chart Data
const pieChartData = [
  { device: "Desktop", percentage: 45 },
  { device: "Mobile", percentage: 38 },
  { device: "Tablet", percentage: 17 },
];
</script>
