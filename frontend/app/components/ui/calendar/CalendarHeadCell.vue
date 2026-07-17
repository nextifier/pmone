<script lang="ts" setup>
import { cn } from "@/lib/utils"
import { reactiveOmit } from "@vueuse/core"
import type { CalendarHeadCellProps } from "reka-ui"
import { CalendarHeadCell, RangeCalendarHeadCell, useForwardProps } from "reka-ui"
import { computed, type HTMLAttributes } from "vue"
import { useCalendarMode } from "./context"

const props = defineProps<CalendarHeadCellProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")

const forwardedProps = useForwardProps(delegatedProps)

const mode = useCalendarMode()
const isRange = computed(() => mode.value === "range")
</script>

<template>
  <component
    :is="isRange ? RangeCalendarHeadCell : CalendarHeadCell"
    data-slot="calendar-head-cell"
    :class="cn('text-muted-foreground flex-1 rounded-(--cell-radius) text-[0.8rem] font-normal select-none', props.class)"
    v-bind="forwardedProps"
  >
    <slot />
  </component>
</template>
