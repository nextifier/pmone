<script lang="ts" setup>
import type { CalendarGridBodyProps } from "reka-ui"
import { CalendarGridBody, RangeCalendarGridBody } from "reka-ui"
import { computed } from "vue"
import { useCalendarMode } from "./context"

const props = defineProps<CalendarGridBodyProps>()

const mode = useCalendarMode()
const isRange = computed(() => mode.value === "range")
</script>

<template>
  <component
    :is="isRange ? RangeCalendarGridBody : CalendarGridBody"
    data-slot="calendar-grid-body"
    v-bind="props"
  >
    <slot />
  </component>
</template>
