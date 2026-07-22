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
    <TooltipContent
      data-slot="tooltip-content"
      v-bind="{ ...forwarded, ...$attrs }"
      :class="cn('cn-tooltip-content cn-tooltip-content-logical bg-foreground text-background z-50 w-fit max-w-xs origin-(--reka-tooltip-content-transform-origin)', props.class)"
    >
      <slot />

      <!-- The offset lives in `cn-tooltip-arrow` (higher specificity), so repeating it
           here was inert. -->
      <TooltipArrow class="cn-tooltip-arrow cn-tooltip-arrow-logical bg-foreground fill-foreground z-50" />
    </TooltipContent>
  </TooltipPortal>
</template>
