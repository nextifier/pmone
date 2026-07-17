<script lang="ts" setup>
import type { CalendarGridHeadProps } from "reka-ui"
import { CalendarGridHead, RangeCalendarGridHead } from "reka-ui"
import { computed, type HTMLAttributes } from "vue"
import { useCalendarMode } from "./context"

const props = defineProps<CalendarGridHeadProps & { class?: HTMLAttributes["class"] }>()

const mode = useCalendarMode()
const isRange = computed(() => mode.value === "range")
</script>

<template>
  <component
    :is="isRange ? RangeCalendarGridHead : CalendarGridHead"
    data-slot="calendar-grid-head"
    v-bind="props"
  >
    <slot />
  </component>
</template>
