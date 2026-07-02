<script setup lang="ts">
import type { MenubarRadioItemEmits, MenubarRadioItemProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { Circle } from "lucide-vue-next"
import {
  MenubarItemIndicator,
  MenubarRadioItem,

  useForwardPropsEmits,
} from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<MenubarRadioItemProps & { class?: HTMLAttributes["class"] }>()
const emits = defineEmits<MenubarRadioItemEmits>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <MenubarRadioItem
    data-slot="menubar-radio-item"
    v-bind="forwarded"
    :class="cn(
      'cn-menubar-radio-item relative flex cursor-pointer items-center outline-hidden select-none data-disabled:pointer-events-none [&_svg]:pointer-events-none [&_svg]:shrink-0',
      props.class,
    )"
  >
    <span class="cn-menubar-radio-item-indicator pointer-events-none absolute flex items-center justify-center">
      <MenubarItemIndicator>
        <Circle class="size-2 fill-current" />
      </MenubarItemIndicator>
    </span>
    <slot />
  </MenubarRadioItem>
</template>
