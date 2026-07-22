<script lang="ts" setup>
import { buttonVariants, type ButtonVariants } from "@/components/ui/button"
import { cn } from "@/lib/utils"
import { reactiveOmit } from "@vueuse/core"
import { ChevronRight } from "lucide-vue-next"
import type { CalendarNextProps } from "reka-ui"
import { CalendarNext, RangeCalendarNext, useForwardProps } from "reka-ui"
import { computed, type HTMLAttributes } from "vue"
import { useCalendarMode } from "./context"

const props = defineProps<
  CalendarNextProps & {
    class?: HTMLAttributes["class"]
    variant?: ButtonVariants["variant"]
  }
>()

const delegatedProps = reactiveOmit(props, "class", "variant")

const forwardedProps = useForwardProps(delegatedProps)

const mode = useCalendarMode()
const isRange = computed(() => mode.value === "range")
</script>

<template>
  <component
    :is="isRange ? RangeCalendarNext : CalendarNext"
    data-slot="calendar-next-button"
    :class="cn(
      buttonVariants({ variant: props.variant ?? 'ghost' }),
      'size-(--cell-size) p-0 select-none aria-disabled:opacity-50',
      props.class,
    )"
    v-bind="forwardedProps"
  >
    <slot>
      <ChevronRight class="size-4 rtl:rotate-180" />
    </slot>
  </component>
</template>
