<template>
  <div class="container pt-4 pb-16">
    <div class="space-y-2">
      <h1 class="page-title">Charts</h1>
      <p class="page-description">
        A collection of reusable chart components built with Unovis visualization library
      </p>
    </div>

    <div v-if="areaChartData.length > 0" class="mt-12 space-y-12">
      <!-- Area Charts -->
      <section class="space-y-6">
        <div class="space-y-2">
          <h2 class="text-2xl font-semibold tracking-tight">Area Charts</h2>
          <p class="text-muted-foreground text-sm">
            Area charts with gradient fills and interactive filters
          </p>
        </div>
        <div class="grid gap-6 md:grid-cols-2">
          <Card v-if="areaChartData">
            <CardHeader>
              <CardTitle>Area Chart - Gradient</CardTitle>
              <CardDescription>Showing total visitors for the last 6 months</CardDescription>
            </CardHeader>
            <CardContent
              class="overflow-hidden px-0! [&_svg>g]:origin-center! [&_svg>g]:not-first:scale-x-110 [&_svg>g]:first:scale-x-90!"
            >
              <ChartAreaGradient :data="areaChartData" :config="areaChartConfig" />
            </CardContent>

            <CardFooter>
              <div class="flex w-full items-start gap-2 text-sm">
                <div class="grid gap-2">
                  <div class="flex items-center gap-2 leading-none font-medium">
                    Trending up by 5.2% this month <Icon name="lucide:trending-up" class="size-4" />
                  </div>
                  <div class="text-muted-foreground flex items-center gap-2 leading-none">
                    January - June 2024
                  </div>
                </div>
              </div>
            </CardFooter>
          </Card>

          <ChartAreaInteractive
            v-if="areaInteractiveChartData"
            :data="areaInteractiveChartData"
            :config="areaChartConfig"
          />
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
          <Card v-if="lineChartData">
            <CardHeader>
              <CardTitle>Line Chart</CardTitle>
              <CardDescription>January - June 2024</CardDescription>
            </CardHeader>
            <CardContent>
              <ChartLineDefault :data="lineChartData" :config="lineChartConfig" />
            </CardContent>
            <CardFooter class="flex-col items-start gap-2 text-sm">
              <div class="flex gap-2 leading-none font-medium">
                Trending up by 5.2% this month <Icon name="lucide:trending-up" class="size-4" />
              </div>
              <div class="text-muted-foreground leading-none">
                Showing total visitors for the last 6 months
              </div>
            </CardFooter>
          </Card>

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
          <ChartPieDonut v-if="pieChartData" :data="pieChartData" :config="pieChartConfig" />
          <ChartPieStacked
            v-if="pieStackedDesktopData && pieStackedMobileData"
            :desktop-data="pieStackedDesktopData"
            :mobile-data="pieStackedMobileData"
            :config="pieStackedChartConfig"
          />
          <ChartPieDonutText v-if="pieChartData" :data="pieChartData" :config="pieChartConfig" />
          <ChartPieSimple v-if="pieChartData" :data="pieChartData" :config="pieChartConfig" />
        </div>
      </section>
    </div>
  </div>
</template>

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

// Area Interactive Chart Data (with Date objects) - 91 days from April to June
const areaInteractiveChartData = [
  { date: new Date("2024-04-01"), desktop: 222, mobile: 150 },
  { date: new Date("2024-04-02"), desktop: 97, mobile: 180 },
  { date: new Date("2024-04-03"), desktop: 167, mobile: 120 },
  { date: new Date("2024-04-04"), desktop: 242, mobile: 260 },
  { date: new Date("2024-04-05"), desktop: 373, mobile: 290 },
  { date: new Date("2024-04-06"), desktop: 301, mobile: 340 },
  { date: new Date("2024-04-07"), desktop: 245, mobile: 180 },
  { date: new Date("2024-04-08"), desktop: 409, mobile: 320 },
  { date: new Date("2024-04-09"), desktop: 59, mobile: 110 },
  { date: new Date("2024-04-10"), desktop: 261, mobile: 190 },
  { date: new Date("2024-04-11"), desktop: 327, mobile: 330 },
  { date: new Date("2024-04-12"), desktop: 292, mobile: 210 },
  { date: new Date("2024-04-13"), desktop: 342, mobile: 380 },
  { date: new Date("2024-04-14"), desktop: 137, mobile: 220 },
  { date: new Date("2024-04-15"), desktop: 120, mobile: 170 },
  { date: new Date("2024-04-16"), desktop: 138, mobile: 190 },
  { date: new Date("2024-04-17"), desktop: 446, mobile: 360 },
  { date: new Date("2024-04-18"), desktop: 364, mobile: 410 },
  { date: new Date("2024-04-19"), desktop: 243, mobile: 180 },
  { date: new Date("2024-04-20"), desktop: 89, mobile: 150 },
  { date: new Date("2024-04-21"), desktop: 137, mobile: 200 },
  { date: new Date("2024-04-22"), desktop: 224, mobile: 170 },
  { date: new Date("2024-04-23"), desktop: 138, mobile: 230 },
  { date: new Date("2024-04-24"), desktop: 387, mobile: 290 },
  { date: new Date("2024-04-25"), desktop: 215, mobile: 250 },
  { date: new Date("2024-04-26"), desktop: 75, mobile: 130 },
  { date: new Date("2024-04-27"), desktop: 383, mobile: 330 },
  { date: new Date("2024-04-28"), desktop: 122, mobile: 210 },
  { date: new Date("2024-04-29"), desktop: 315, mobile: 270 },
  { date: new Date("2024-04-30"), desktop: 235, mobile: 190 },
  { date: new Date("2024-05-01"), desktop: 177, mobile: 100 },
  { date: new Date("2024-05-02"), desktop: 82, mobile: 155 },
  { date: new Date("2024-05-03"), desktop: 252, mobile: 200 },
  { date: new Date("2024-05-04"), desktop: 294, mobile: 220 },
  { date: new Date("2024-05-05"), desktop: 147, mobile: 170 },
  { date: new Date("2024-05-06"), desktop: 247, mobile: 270 },
  { date: new Date("2024-05-07"), desktop: 108, mobile: 210 },
  { date: new Date("2024-05-08"), desktop: 191, mobile: 290 },
  { date: new Date("2024-05-09"), desktop: 335, mobile: 250 },
  { date: new Date("2024-05-10"), desktop: 197, mobile: 220 },
  { date: new Date("2024-05-11"), desktop: 70, mobile: 240 },
  { date: new Date("2024-05-12"), desktop: 290, mobile: 130 },
  { date: new Date("2024-05-13"), desktop: 246, mobile: 200 },
  { date: new Date("2024-05-14"), desktop: 195, mobile: 150 },
  { date: new Date("2024-05-15"), desktop: 159, mobile: 120 },
  { date: new Date("2024-05-16"), desktop: 355, mobile: 170 },
  { date: new Date("2024-05-17"), desktop: 129, mobile: 80 },
  { date: new Date("2024-05-18"), desktop: 340, mobile: 200 },
  { date: new Date("2024-05-19"), desktop: 23, mobile: 120 },
  { date: new Date("2024-05-20"), desktop: 85, mobile: 80 },
  { date: new Date("2024-05-21"), desktop: 217, mobile: 270 },
  { date: new Date("2024-05-22"), desktop: 237, mobile: 210 },
  { date: new Date("2024-05-23"), desktop: 206, mobile: 180 },
  { date: new Date("2024-05-24"), desktop: 215, mobile: 270 },
  { date: new Date("2024-05-25"), desktop: 201, mobile: 250 },
  { date: new Date("2024-05-26"), desktop: 117, mobile: 230 },
  { date: new Date("2024-05-27"), desktop: 142, mobile: 240 },
  { date: new Date("2024-05-28"), desktop: 213, mobile: 290 },
  { date: new Date("2024-05-29"), desktop: 191, mobile: 200 },
  { date: new Date("2024-05-30"), desktop: 179, mobile: 100 },
  { date: new Date("2024-05-31"), desktop: 75, mobile: 228 },
  { date: new Date("2024-06-01"), desktop: 383, mobile: 355 },
  { date: new Date("2024-06-02"), desktop: 101, mobile: 171 },
  { date: new Date("2024-06-03"), desktop: 323, mobile: 201 },
  { date: new Date("2024-06-04"), desktop: 233, mobile: 247 },
  { date: new Date("2024-06-05"), desktop: 136, mobile: 247 },
  { date: new Date("2024-06-06"), desktop: 252, mobile: 290 },
  { date: new Date("2024-06-07"), desktop: 292, mobile: 330 },
  { date: new Date("2024-06-08"), desktop: 382, mobile: 350 },
  { date: new Date("2024-06-09"), desktop: 321, mobile: 271 },
  { date: new Date("2024-06-10"), desktop: 233, mobile: 187 },
  { date: new Date("2024-06-11"), desktop: 105, mobile: 139 },
  { date: new Date("2024-06-12"), desktop: 214, mobile: 290 },
  { date: new Date("2024-06-13"), desktop: 188, mobile: 250 },
  { date: new Date("2024-06-14"), desktop: 239, mobile: 210 },
  { date: new Date("2024-06-15"), desktop: 322, mobile: 310 },
  { date: new Date("2024-06-16"), desktop: 165, mobile: 270 },
  { date: new Date("2024-06-17"), desktop: 171, mobile: 299 },
  { date: new Date("2024-06-18"), desktop: 214, mobile: 315 },
  { date: new Date("2024-06-19"), desktop: 182, mobile: 210 },
  { date: new Date("2024-06-20"), desktop: 242, mobile: 285 },
  { date: new Date("2024-06-21"), desktop: 191, mobile: 197 },
  { date: new Date("2024-06-22"), desktop: 201, mobile: 202 },
  { date: new Date("2024-06-23"), desktop: 344, mobile: 340 },
  { date: new Date("2024-06-24"), desktop: 383, mobile: 370 },
  { date: new Date("2024-06-25"), desktop: 392, mobile: 313 },
  { date: new Date("2024-06-26"), desktop: 201, mobile: 245 },
  { date: new Date("2024-06-27"), desktop: 193, mobile: 229 },
  { date: new Date("2024-06-28"), desktop: 286, mobile: 290 },
  { date: new Date("2024-06-29"), desktop: 289, mobile: 342 },
  { date: new Date("2024-06-30"), desktop: 340, mobile: 297 },
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
