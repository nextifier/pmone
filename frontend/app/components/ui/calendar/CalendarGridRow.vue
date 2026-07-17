<script lang="ts" setup>
import { cn } from "@/lib/utils"
import { reactiveOmit } from "@vueuse/core"
import type { CalendarGridRowProps } from "reka-ui"
import { CalendarGridRow, RangeCalendarGridRow, useForwardProps } from "reka-ui"
import { computed, type HTMLAttributes } from "vue"
import { useCalendarMode } from "./context"

const props = defineProps<CalendarGridRowProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")

const forwardedProps = useForwardProps(delegatedProps)

const mode = useCalendarMode()
const isRange = computed(() => mode.value === "range")
</script>

<template>
  <component
    :is="isRange ? RangeCalendarGridRow : CalendarGridRow"
    data-slot="calendar-grid-row"
    :class="cn('flex', props.class)"
    v-bind="forwardedProps"
  >
    <slot />
  </component>
</template>
