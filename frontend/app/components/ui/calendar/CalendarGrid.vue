<script lang="ts" setup>
import { cn } from "@/lib/utils"
import { reactiveOmit } from "@vueuse/core"
import type { CalendarGridProps } from "reka-ui"
import { CalendarGrid, RangeCalendarGrid, useForwardProps } from "reka-ui"
import { computed, type HTMLAttributes } from "vue"
import { useCalendarMode } from "./context"

const props = defineProps<CalendarGridProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")

const forwardedProps = useForwardProps(delegatedProps)

const mode = useCalendarMode()
const isRange = computed(() => mode.value === "range")
</script>

<template>
  <component
    :is="isRange ? RangeCalendarGrid : CalendarGrid"
    data-slot="calendar-grid"
    :class="cn('w-full border-collapse', props.class)"
    v-bind="forwardedProps"
  >
    <slot />
  </component>
</template>
