<script setup lang="ts">
import type { ContextMenuRadioItemEmits, ContextMenuRadioItemProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { Check } from "lucide-vue-next"
import {
  ContextMenuItemIndicator,
  ContextMenuRadioItem,

  useForwardPropsEmits,
} from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<ContextMenuRadioItemProps & { class?: HTMLAttributes["class"] }>()
const emits = defineEmits<ContextMenuRadioItemEmits>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <ContextMenuRadioItem
    data-slot="context-menu-radio-item"
    v-bind="forwarded"
    :class="cn(
      'cn-context-menu-radio-item relative flex cursor-pointer items-center outline-hidden select-none data-disabled:pointer-events-none data-disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0',
      props.class,
    )"
  >
    <span class="cn-context-menu-item-indicator pointer-events-none">
      <ContextMenuItemIndicator>
        <Check class="size-4" />
      </ContextMenuItemIndicator>
    </span>
    <slot />
  </ContextMenuRadioItem>
</template>
