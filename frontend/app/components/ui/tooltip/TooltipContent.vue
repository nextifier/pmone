<script setup lang="ts">
import type { TooltipContentEmits, TooltipContentProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { TooltipArrow, TooltipContent, TooltipPortal, useForwardPropsEmits } from "reka-ui"
import { cn } from "@/lib/utils"

defineOptions({
  inheritAttrs: false,
})

const props = withDefaults(defineProps<TooltipContentProps & { class?: HTMLAttributes["class"] }>(), {
  sideOffset: 4,
})

const emits = defineEmits<TooltipContentEmits>()

const delegatedProps = reactiveOmit(props, "class")
const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <TooltipPortal>
    <!-- Motion from transitions-dev (17-tooltip): appears on the slow clock, leaves
         much faster. Reka never sets data-state="open" on a tooltip - it is either
         delayed-open or instant-open - so both are spelled out here; only the exit
         matches data-closed. -->
    <TooltipContent
      data-slot="tooltip-content"
      v-bind="{ ...forwarded, ...$attrs }"
      :class="
        cn(
          'cn-tooltip-content cn-tooltip-content-logical bg-foreground text-background z-50 w-fit max-w-xs origin-(--reka-tooltip-content-transform-origin)',
          'data-[state=delayed-open]:duration-(--tt-in-dur) data-[state=delayed-open]:zoom-in-98 data-[state=instant-open]:duration-(--tt-in-dur) data-[state=instant-open]:zoom-in-98 ease-(--tt-in-ease) data-closed:duration-(--tt-out-dur) data-closed:zoom-out-98 data-closed:ease-(--tt-out-ease) motion-reduce:animate-none!',
          props.class,
        )
      "
    >
      <slot />

      <!-- The offset lives in `cn-tooltip-arrow` (higher specificity), so repeating it
           here was inert. -->
      <TooltipArrow class="cn-tooltip-arrow cn-tooltip-arrow-logical bg-foreground fill-foreground z-50" />
    </TooltipContent>
  </TooltipPortal>
</template>
