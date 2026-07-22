<script lang="ts" setup>
import { buttonVariants, type ButtonVariants } from "@/components/ui/button"
import { cn } from "@/lib/utils"
import { reactiveOmit } from "@vueuse/core"
import { ChevronLeft } from "lucide-vue-next"
import type { CalendarPrevProps } from "reka-ui"
import { CalendarPrev, RangeCalendarPrev, useForwardProps } from "reka-ui"
import { computed, type HTMLAttributes } from "vue"
import { useCalendarMode } from "./context"

const props = defineProps<
  CalendarPrevProps & {
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
    :is="isRange ? RangeCalendarPrev : CalendarPrev"
    data-slot="calendar-prev-button"
    :class="cn(
      buttonVariants({ variant: props.variant ?? 'ghost' }),
      'size-(--cell-size) p-0 select-none aria-disabled:opacity-50',
      props.class,
    )"
    v-bind="forwardedProps"
  >
    <slot>
      <ChevronLeft class="size-4 rtl:rotate-180" />
    </slot>
  </component>
</template>
