<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { computed, ref, onMounted } from "vue"
import { cn } from "@/lib/utils"
import { useChart } from "."

const props = withDefaults(defineProps<{
  hideIcon?: boolean
  nameKey?: string
  verticalAlign?: "bottom" | "top"
  class?: HTMLAttributes["class"]
}>(), {
  verticalAlign: "bottom",
})

const { id, config } = useChart()

// Only entries that carry a color/theme are real series; skip value-meta
// entries (e.g. { count: { label: "Tasks" } }) so they don't show as a
// colorless legend item.
const payload = computed(() => Object.entries(config.value)
  .filter(([, value]) => value.color || value.theme)
  .map(([key]) => {
    return {
      key: props.nameKey || key,
      itemConfig: config.value[key],
    }
  }))

const containerSelector = ref("")
onMounted(() => {
  containerSelector.value = `[data-chart="chart-${id}"]>[data-vis-xy-container]`
})
</script>

<template>
  <div
    v-if="containerSelector"
    :class="cn(
      'flex flex-wrap items-center justify-center gap-x-3 gap-y-1',
      verticalAlign === 'top' ? 'pb-3' : 'pt-3',
      props.class,
    )"
  >
    <div
      v-for="{ key, itemConfig } in payload"
      :key="key"
      :class="cn(
        '[&>svg]:text-muted-foreground flex shrink-0 items-center gap-1.5 whitespace-nowrap [&>svg]:h-3 [&>svg]:w-3',
      )"
    >
      <component :is="itemConfig.icon" v-if="itemConfig?.icon" />
      <div
        v-else
        class="h-2 w-2 shrink-0 rounded-[2px]"
        :style="{
          backgroundColor: itemConfig.color,
        }"
      />
      {{ itemConfig?.label }}
    </div>
  </div>
</template>
