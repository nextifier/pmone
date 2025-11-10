<script setup lang="ts">
import { VisDonut, VisSingleContainer } from "@unovis/vue"
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
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
} from "@/components/ui/chart"

const props = defineProps<{
  devices: Array<{
    device: string
    users: number
    sessions: number
  }>
}>()

type Data = {
  label: string
  value: number
  fill: string
}

const chartData = computed<Data[]>(() => {
  if (!props.devices || props.devices.length === 0) return []

  const colorMap: Record<string, string> = {
    'desktop': 'var(--chart-1)',
    'mobile': 'var(--chart-2)',
    'tablet': 'var(--chart-3)',
  }

  return props.devices.map(device => ({
    label: device.device.charAt(0).toUpperCase() + device.device.slice(1),
    value: device.users || 0,
    fill: colorMap[device.device.toLowerCase()] || 'var(--chart-4)'
  }))
})

const chartConfig = computed(() => {
  const config: ChartConfig = {}

  chartData.value.forEach((item) => {
    config[item.label.toLowerCase()] = {
      label: item.label,
      color: item.fill
    }
  })

  return config
})

const totalUsers = computed(() => {
  return chartData.value.reduce((sum, item) => sum + item.value, 0)
})

const getPercentage = (value: number) => {
  if (totalUsers.value === 0) return '0%'
  return ((value / totalUsers.value) * 100).toFixed(1) + '%'
}
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle>Device Breakdown</CardTitle>
      <CardDescription>
        Visitors by device type
      </CardDescription>
    </CardHeader>
    <CardContent>
      <ChartContainer :config="chartConfig" class="min-h-[200px] w-full mx-auto aspect-square max-h-[250px]">
        <VisSingleContainer
          v-if="chartData.length > 0"
          :data="chartData"
        >
          <VisDonut
            :value="(d: Data) => d.value"
            :arc-width="0"
            :pad-angle="0.02"
            :corner-radius="3"
            :color="(d: Data) => d.fill"
          />
          <ChartTooltip>
            <ChartTooltipContent
              :label-formatter="(label: string, payload: any) => {
                const item = payload?.[0]?.payload
                return item?.label || label
              }"
              :formatter="(value: number, name: string, item: any) => {
                const percentage = getPercentage(value)
                return `${value.toLocaleString()} users (${percentage})`
              }"
            />
          </ChartTooltip>
        </VisSingleContainer>
        <div v-else class="flex items-center justify-center py-8 text-sm text-muted-foreground">
          No device data available
        </div>

        <ChartLegendContent v-if="chartData.length > 0" class="mt-4" />
      </ChartContainer>
    </CardContent>
  </Card>
</template>
