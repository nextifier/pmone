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
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
  componentToString,
} from "@/components/ui/chart"

const props = defineProps<{
  properties: Array<{
    property_id: string
    property_name: string
    metrics: {
      activeUsers?: number
      sessions?: number
      screenPageViews?: number
      newUsers?: number
      bounceRate?: number
      averageSessionDuration?: number
    }
    project?: {
      id: number
      name: string
      profile_image?: any
    }
  }>
}>()

type Data = {
  name: string
  users: number
  sessions: number
}

const chartData = computed<Data[]>(() => {
  if (!props.properties || props.properties.length === 0) return []

  // Take top 10 properties by users
  return props.properties
    .slice(0, 10)
    .map(property => ({
      name: property.property_name || property.project?.name || 'Unknown',
      users: property.metrics?.activeUsers || 0,
      sessions: property.metrics?.sessions || 0
    }))
})

const chartConfig = {
  users: {
    label: "Users",
    color: "var(--chart-1)",
  },
  sessions: {
    label: "Sessions",
    color: "var(--chart-2)",
  },
} satisfies ChartConfig
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle>Property Performance</CardTitle>
      <CardDescription>
        Comparison of users and sessions across properties
      </CardDescription>
    </CardHeader>
    <CardContent>
      <ChartContainer :config="chartConfig" class="min-h-[200px] w-full">
        <VisXYContainer
          v-if="chartData.length > 0"
          :data="chartData"
        >
          <VisGroupedBar
            :x="(d: Data, i: number) => i"
            :y="[(d: Data) => d.users, (d: Data) => d.sessions]"
            :color="[chartConfig.users.color, chartConfig.sessions.color]"
            :rounded-corners="4"
            bar-padding="0.15"
            group-padding="0"
          />
          <VisAxis
            type="x"
            :x="(d: Data, i: number) => i"
            :tick-line="false"
            :domain-line="false"
            :grid-line="false"
            :num-ticks="chartData.length"
            :tick-format="(value: number, index: number) => {
              const item = chartData[index]
              if (!item) return ''
              const label = item.name
              return label.length > 15 ? label.substring(0, 15) + '...' : label
            }"
          />
          <VisAxis
            type="y"
            :num-ticks="5"
            :tick-line="false"
            :domain-line="false"
          />
          <ChartTooltip />
          <ChartCrosshair
            :template="componentToString(chartConfig, ChartTooltipContent, { indicator: 'dashed', hideLabel: true })"
            color="#0000"
          />
        </VisXYContainer>
        <div v-else class="flex items-center justify-center py-8 text-sm text-muted-foreground">
          No property data available
        </div>

        <ChartLegendContent v-if="chartData.length > 0" class="mt-4" />
      </ChartContainer>
    </CardContent>
  </Card>
</template>
