<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import type { ChartConfig } from "."
import { useId } from "reka-ui"
import { cn } from "@/lib/utils"
import { provideChartContext } from "."
import ChartStyle from "./ChartStyle.vue"

const props = defineProps<{
  id?: HTMLAttributes["id"]
  class?: HTMLAttributes["class"]
  config: ChartConfig
  cursor?: boolean
}>()

const { config } = toRefs(props)
const uniqueId = useId()
const chartId = computed(() => `chart-${props.id || uniqueId.replace(/:/g, "")}`)

provideChartContext({ id: uniqueId, config })
</script>

<template>
  <div
    data-slot="chart"
    :data-chart="chartId"
    :class="cn(
      '[&_.tick_text]:!fill-muted-foreground [&_.tick_line]:!stroke-border/50 flex flex-col aspect-video justify-center text-xs h-full w-full',
      props.class,
    )"
    :style="{
      '--vis-tooltip-padding': '0px',
      '--vis-tooltip-background-color': 'transparent',
      '--vis-tooltip-border-color': 'transparent',
      '--vis-crosshair-line-stroke-width': cursor ? '1px' : '0px',
      '--vis-font-family': 'var(--font-sans)',
    }"
  >
    <slot :id="uniqueId" :config="config" />
    <ChartStyle :id="chartId" />
  </div>
</template>
