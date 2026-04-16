<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { useResizeObserver } from "@vueuse/core"
import { cn } from "@/lib/utils"

type Breakpoint = "xs" | "sm" | "md" | "lg" | "xl" | "2xl" | "3xl"
type Rounded = "sm" | "md" | "lg" | "xl" | "2xl" | "3xl"

const roundedClass: Record<Rounded, string> = {
  sm: "rounded-sm overflow-hidden",
  md: "rounded-md overflow-hidden",
  lg: "rounded-lg overflow-hidden",
  xl: "rounded-xl overflow-hidden",
  "2xl": "rounded-2xl overflow-hidden",
  "3xl": "rounded-3xl overflow-hidden",
}

const breakpointAutoFitClass: Record<Breakpoint, string> = {
  xs: "xs:grid-cols-[var(--grid-fill-cols-auto)]",
  sm: "sm:grid-cols-[var(--grid-fill-cols-auto)]",
  md: "md:grid-cols-[var(--grid-fill-cols-auto)]",
  lg: "lg:grid-cols-[var(--grid-fill-cols-auto)]",
  xl: "xl:grid-cols-[var(--grid-fill-cols-auto)]",
  "2xl": "2xl:grid-cols-[var(--grid-fill-cols-auto)]",
  "3xl": "3xl:grid-cols-[var(--grid-fill-cols-auto)]",
}

const props = withDefaults(
  defineProps<{
    count: number
    class?: HTMLAttributes["class"]
    fillerClass?: HTMLAttributes["class"]
    minColWidth?: string | false
    cols?: number
    breakpoint?: Breakpoint
    rounded?: Rounded
  }>(),
  {
    fillerClass: "bg-pattern-diagonal",
    minColWidth: "180px",
    cols: 2,
    breakpoint: "sm",
  },
)

const gridRef = ref<HTMLElement | null>(null)
const columnCount = ref(0)

const fillerCount = computed(() => {
  if (!columnCount.value || props.count === 0) return 0
  const remainder = props.count % columnCount.value
  return remainder === 0 ? 0 : columnCount.value - remainder
})

const gridStyle = computed(() => {
  const base = `repeat(${props.cols}, minmax(0, 1fr))`
  if (props.minColWidth === false) {
    return { gridTemplateColumns: base }
  }
  return {
    "--grid-fill-cols": base,
    "--grid-fill-cols-auto": `repeat(auto-fit, minmax(${props.minColWidth}, 1fr))`,
  } as Record<string, string>
})

function detectColumns() {
  if (!gridRef.value) return
  columnCount.value = getComputedStyle(gridRef.value).gridTemplateColumns.split(" ").length
}

useResizeObserver(gridRef, () => detectColumns())
</script>

<template>
  <div
    ref="gridRef"
    data-slot="grid-fill"
    :style="gridStyle"
    :class="
      cn(
        'bg-border *:bg-background relative grid gap-px border border-border *:relative',
        minColWidth !== false
          ? ['grid-cols-[var(--grid-fill-cols)]', breakpointAutoFitClass[breakpoint]]
          : 'grid-cols-[var(--grid-fill-cols)]',
        rounded && roundedClass[rounded],
        props.class,
      )
    "
  >
    <slot />
    <template v-for="i in fillerCount" :key="`filler-${i}`">
      <slot name="filler" :index="i">
        <div :class="cn(fillerClass)" />
      </slot>
    </template>
  </div>
</template>
