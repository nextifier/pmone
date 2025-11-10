<script setup lang="ts">
import { VisAxis, VisGroupedBar, VisXYContainer } from "@unovis/vue"
import type { ChartConfig } from "@/components/ui/chart"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import {
  ChartContainer,
  ChartCrosshair,
  ChartTooltip,
  ChartTooltipContent,
  componentToString,
} from "@/components/ui/chart"
import { Orientation } from "@unovis/ts"

const props = defineProps<{
  topPages: Array<{
    pageTitle: string
    pagePath: string
    pageviews: number
    property_id?: string
    property_name?: string
  }>
}>()

type Data = {
  label: string
  pageviews: number
}

const chartData = computed<Data[]>(() => {
  if (!props.topPages || props.topPages.length === 0) return []

  // Take top 10 pages
  return props.topPages.slice(0, 10).map(page => ({
    label: page.pageTitle || page.pagePath || 'Unknown',
    pageviews: page.pageviews || 0
  }))
})

const chartConfig = {
  pageviews: {
    label: "Page Views",
    color: "var(--chart-1)",
  },
} satisfies ChartConfig

const maxPageviews = computed(() => {
  if (chartData.value.length === 0) return 1
  return Math.max(...chartData.value.map(d => d.pageviews))
})
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle>Top Pages</CardTitle>
      <CardDescription>
        Most visited pages across all properties
      </CardDescription>
    </CardHeader>
    <CardContent>
      <ChartContainer :config="chartConfig" class="min-h-[200px] w-full">
        <VisXYContainer
          v-if="chartData.length > 0"
          :data="chartData"
          :margin="{ left: 10, right: 10 }"
        >
          <VisGroupedBar
            :x="(d: Data) => d.pageviews"
            :y="(d: Data, i: number) => i"
            :color="chartConfig.pageviews.color"
            :rounded-corners="4"
            :orientation="Orientation.Horizontal"
          />
          <VisAxis
            type="y"
            :y="(d: Data, i: number) => i"
            :tick-line="false"
            :domain-line="false"
            :grid-line="false"
            :num-ticks="chartData.length"
            :tick-format="(value: number, index: number) => {
              const item = chartData[index]
              if (!item) return ''
              const label = item.label
              return label.length > 30 ? label.substring(0, 30) + '...' : label
            }"
          />
          <VisAxis
            type="x"
            :x="(d: Data) => d.pageviews"
            :tick-line="false"
            :domain-line="false"
            :num-ticks="5"
          />
          <ChartTooltip />
          <ChartCrosshair
            :template="componentToString(chartConfig, ChartTooltipContent, { hideLabel: true })"
            color="#0000"
          />
        </VisXYContainer>
        <div v-else class="flex items-center justify-center py-8 text-sm text-muted-foreground">
          No page data available
        </div>
      </ChartContainer>
    </CardContent>
  </Card>
</template>
