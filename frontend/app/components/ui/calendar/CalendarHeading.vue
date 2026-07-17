<script lang="ts" setup>
import { cn } from "@/lib/utils"
import { reactiveOmit } from "@vueuse/core"
import type { CalendarHeadingProps } from "reka-ui"
import { CalendarHeading, RangeCalendarHeading, useForwardProps } from "reka-ui"
import { computed, type HTMLAttributes } from "vue"
import { useCalendarMode } from "./context"

const props = defineProps<CalendarHeadingProps & { class?: HTMLAttributes["class"] }>()

defineSlots<{
  default: (props: { headingValue: string }) => any
}>()

const delegatedProps = reactiveOmit(props, "class")

const forwardedProps = useForwardProps(delegatedProps)

const mode = useCalendarMode()
const isRange = computed(() => mode.value === "range")
</script>

<template>
  <component
    :is="isRange ? RangeCalendarHeading : CalendarHeading"
    v-slot="{ headingValue }"
    data-slot="calendar-heading"
    :class="cn('cn-calendar-caption text-sm font-medium select-none', props.class)"
    v-bind="forwardedProps"
  >
    <slot :heading-value>
      {{ headingValue }}
    </slot>
  </component>
</template>
