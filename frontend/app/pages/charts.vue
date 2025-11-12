<script setup>
usePageMeta("", {
  title: "Charts",
  description: "A collection of chart components built with Unovis",
});

// Area Chart Data
const areaChartData = [
  { month: 1, monthLabel: "January", desktop: 186, mobile: 80 },
  { month: 2, monthLabel: "February", desktop: 305, mobile: 200 },
  { month: 3, monthLabel: "March", desktop: 237, mobile: 120 },
  { month: 4, monthLabel: "April", desktop: 73, mobile: 190 },
  { month: 5, monthLabel: "May", desktop: 209, mobile: 130 },
  { month: 6, monthLabel: "June", desktop: 214, mobile: 140 },
];

const areaChartConfig = {
  desktop: {
    label: "Desktop",
    color: "var(--chart-1)",
  },
  mobile: {
    label: "Mobile",
    color: "var(--chart-2)",
  },
};

// Area Interactive Chart Data (with Date objects)
const areaInteractiveChartData = [
  { date: new Date("2024-01-01"), desktop: 186, mobile: 80 },
  { date: new Date("2024-02-01"), desktop: 305, mobile: 200 },
  { date: new Date("2024-03-01"), desktop: 237, mobile: 120 },
  { date: new Date("2024-04-01"), desktop: 73, mobile: 190 },
  { date: new Date("2024-05-01"), desktop: 209, mobile: 130 },
  { date: new Date("2024-06-01"), desktop: 214, mobile: 140 },
];

// Bar Chart Data
const barChartData = [
  { date: new Date("2024-01-01"), desktop: 222, mobile: 150 },
  { date: new Date("2024-02-01"), desktop: 97, mobile: 180 },
  { date: new Date("2024-03-01"), desktop: 167, mobile: 120 },
  { date: new Date("2024-04-01"), desktop: 242, mobile: 260 },
  { date: new Date("2024-05-01"), desktop: 373, mobile: 290 },
  { date: new Date("2024-06-01"), desktop: 301, mobile: 340 },
];

const barChartConfig = {
  desktop: {
    label: "Desktop",
    color: "var(--chart-1)",
  },
  mobile: {
    label: "Mobile",
    color: "var(--chart-2)",
  },
};

// Line Chart Data
const lineChartData = [
  { date: new Date("2024-01-01"), desktop: 186 },
  { date: new Date("2024-02-01"), desktop: 305 },
  { date: new Date("2024-03-01"), desktop: 237 },
  { date: new Date("2024-04-01"), desktop: 73 },
  { date: new Date("2024-05-01"), desktop: 209 },
  { date: new Date("2024-06-01"), desktop: 214 },
];

const lineChartConfig = {
  desktop: {
    label: "Desktop",
    color: "var(--chart-1)",
  },
};

// Pie Chart Data
const pieChartData = [
  { browser: "chrome", visitors: 275, fill: "var(--color-chrome)" },
  { browser: "safari", visitors: 200, fill: "var(--color-safari)" },
  { browser: "firefox", visitors: 187, fill: "var(--color-firefox)" },
  { browser: "edge", visitors: 173, fill: "var(--color-edge)" },
  { browser: "other", visitors: 90, fill: "var(--color-other)" },
];

const pieChartConfig = {
  visitors: {
    label: "Visitors",
  },
  chrome: {
    label: "Chrome",
    color: "var(--chart-1)",
  },
  safari: {
    label: "Safari",
    color: "var(--chart-2)",
  },
  firefox: {
    label: "Firefox",
    color: "var(--chart-3)",
  },
  edge: {
    label: "Edge",
    color: "var(--chart-4)",
  },
  other: {
    label: "Other",
    color: "var(--chart-5)",
  },
};

// Pie Stacked Chart Data
const pieStackedDesktopData = [
  { month: "january", desktop: 186, fill: "var(--color-january)" },
  { month: "february", desktop: 305, fill: "var(--color-february)" },
  { month: "march", desktop: 237, fill: "var(--color-march)" },
  { month: "april", desktop: 173, fill: "var(--color-april)" },
  { month: "may", desktop: 209, fill: "var(--color-may)" },
];

const pieStackedMobileData = [
  { month: "january", mobile: 80, fill: "var(--color-january)" },
  { month: "february", mobile: 200, fill: "var(--color-february)" },
  { month: "march", mobile: 120, fill: "var(--color-march)" },
  { month: "april", mobile: 190, fill: "var(--color-april)" },
  { month: "may", mobile: 130, fill: "var(--color-may)" },
];

const pieStackedChartConfig = {
  visitors: {
    label: "Visitors",
  },
  desktop: {
    label: "Desktop",
  },
  mobile: {
    label: "Mobile",
  },
  january: {
    label: "January",
    color: "var(--chart-1)",
  },
  february: {
    label: "February",
    color: "var(--chart-2)",
  },
  march: {
    label: "March",
    color: "var(--chart-3)",
  },
  april: {
    label: "April",
    color: "var(--chart-4)",
  },
  may: {
    label: "May",
    color: "var(--chart-5)",
  },
};
</script>

<template>
  <div class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
    <div class="space-y-2">
      <h1 class="page-title">Charts</h1>
      <p class="page-description">
        A collection of reusable chart components built with Unovis visualization library
      </p>
    </div>

    <div v-if="areaChartData.length > 0" class="space-y-12">
      <!-- Area Charts -->
      <section class="space-y-6">
        <div class="space-y-2">
          <h2 class="text-2xl font-semibold tracking-tight">Area Charts</h2>
          <p class="text-muted-foreground text-sm">
            Area charts with gradient fills and interactive filters
          </p>
        </div>
        <div class="grid gap-6 md:grid-cols-2">
          <ChartAreaGradient v-if="areaChartData" :data="areaChartData" :config="areaChartConfig" />
          <!-- <ChartAreaInteractive v-if="areaInteractiveChartData" :data="areaInteractiveChartData" :config="areaChartConfig" /> -->
          <ChartAreaAxes v-if="areaChartData" :data="areaChartData" :config="areaChartConfig" />
          <ChartAreaIcons v-if="areaChartData" :data="areaChartData" :config="areaChartConfig" />
        </div>
      </section>

      <!-- Bar Charts -->
      <section class="space-y-6">
        <div class="space-y-2">
          <h2 class="text-2xl font-semibold tracking-tight">Bar Charts</h2>
          <p class="text-muted-foreground text-sm">
            Vertical and horizontal bar charts for comparing data
          </p>
        </div>
        <div class="grid gap-6 md:grid-cols-2">
          <ChartBarDefault v-if="barChartData" :data="barChartData" :config="barChartConfig" />
          <ChartBarMultiple v-if="barChartData" :data="barChartData" :config="barChartConfig" />
          <ChartBarHorizontal v-if="barChartData" :data="barChartData" :config="barChartConfig" />
        </div>
      </section>

      <!-- Line Charts -->
      <section class="space-y-6">
        <div class="space-y-2">
          <h2 class="text-2xl font-semibold tracking-tight">Line Charts</h2>
          <p class="text-muted-foreground text-sm">
            Line charts with natural curves, linear, and step styles
          </p>
        </div>
        <div class="grid gap-6 md:grid-cols-2">
          <ChartLineDefault v-if="lineChartData" :data="lineChartData" :config="lineChartConfig" />
          <ChartLineStep v-if="lineChartData" :data="lineChartData" :config="lineChartConfig" />
          <ChartLineLinear v-if="lineChartData" :data="lineChartData" :config="lineChartConfig" />
        </div>
      </section>

      <!-- Pie Charts -->
      <section class="space-y-6">
        <div class="space-y-2">
          <h2 class="text-2xl font-semibold tracking-tight">Pie Charts</h2>
          <p class="text-muted-foreground text-sm">
            Donut and pie charts with various styles and nested sections
          </p>
        </div>
        <div class="grid gap-6 md:grid-cols-2">
          <!-- <ChartPieDonut v-if="pieChartData" :data="pieChartData" :config="pieChartConfig" /> -->
          <ChartPieStacked
            v-if="pieStackedDesktopData && pieStackedMobileData"
            :desktop-data="pieStackedDesktopData"
            :mobile-data="pieStackedMobileData"
            :config="pieStackedChartConfig"
          />
          <!-- <ChartPieDonutText v-if="pieChartData" :data="pieChartData" :config="pieChartConfig" /> -->
          <ChartPieSimple v-if="pieChartData" :data="pieChartData" :config="pieChartConfig" />
        </div>
      </section>
    </div>
  </div>
</template>
