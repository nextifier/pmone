<script lang="ts" setup>
import { buttonVariants } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { reactiveOmit } from '@vueuse/core'
import type { RangeCalendarCellTriggerProps } from 'reka-ui'
import { RangeCalendarCellTrigger, useForwardProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'

const props = withDefaults(
  defineProps<RangeCalendarCellTriggerProps & { class?: HTMLAttributes['class'] }>(),
  {
    as: 'button'
  }
)

const delegatedProps = reactiveOmit(props, 'class')

const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <RangeCalendarCellTrigger
    data-slot="range-calendar-trigger"
    :class="
      cn(
        buttonVariants({ variant: 'ghost' }),
        'relative size-8 cursor-pointer p-0 font-normal aria-selected:opacity-100',
        '[&[data-today]:not([data-selected])]:bg-muted [&[data-today]:not([data-selected])]:text-foreground',
        // Selected (entire range)
        'data-[selected]:text-primary-foreground data-[selected]:hover:text-primary-foreground data-[selected]:focus:text-primary-foreground data-[selected]:opacity-100',
        // Selection Start
        'data-[selection-start]:bg-primary data-[selection-start]:text-primary-foreground data-[selection-start]:hover:bg-primary data-[selection-start]:hover:text-primary-foreground data-[selection-start]:focus:bg-primary data-[selection-start]:focus:text-primary-foreground',
        // Selection End
        'data-[selection-end]:bg-primary data-[selection-end]:text-primary-foreground data-[selection-end]:hover:bg-primary data-[selection-end]:hover:text-primary-foreground data-[selection-end]:focus:bg-primary data-[selection-end]:focus:text-primary-foreground',
        // Disabled
        'data-[disabled]:text-muted-foreground data-[disabled]:opacity-50',
        // Unavailable
        'data-[unavailable]:text-destructive-foreground data-[unavailable]:line-through',
        // Outside months
        'data-[outside-view]:text-muted-foreground',
        // Today dot indicator
        'data-[today]:after:bg-primary data-[today]:data-[selected]:after:bg-primary-foreground data-[today]:after:absolute data-[today]:after:bottom-[3px] data-[today]:after:left-1/2 data-[today]:after:size-[3px] data-[today]:after:-translate-x-1/2 data-[today]:after:rounded-full',
        props.class
      )
    "
    v-bind="forwardedProps"
  >
    <slot />
  </RangeCalendarCellTrigger>
</template>
