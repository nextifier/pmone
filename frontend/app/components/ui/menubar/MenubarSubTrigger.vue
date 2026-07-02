<script setup lang="ts">
import type { MenubarSubTriggerProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { ChevronRight } from "lucide-vue-next"
import { MenubarSubTrigger, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<MenubarSubTriggerProps & { class?: HTMLAttributes["class"], inset?: boolean }>()

const delegatedProps = reactiveOmit(props, "class", "inset")
const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <MenubarSubTrigger
    data-slot="menubar-sub-trigger"
    :data-inset="inset ? '' : undefined"
    v-bind="forwardedProps"
    :class="cn(
      'cn-menubar-sub-trigger flex cursor-pointer items-center outline-none select-none',
      props.class,
    )"
  >
    <slot />
    <ChevronRight class="ml-auto size-4" />
  </MenubarSubTrigger>
</template>
