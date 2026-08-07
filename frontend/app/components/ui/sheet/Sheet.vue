<script setup lang="ts">
import type { DialogRootEmits, DialogRootProps } from "reka-ui"
import { DialogRoot, useForwardProps } from "reka-ui"
import { useVModel } from "@vueuse/core"
import { usePanelHistory } from "@/components/ui/panel-history"

const props = defineProps<DialogRootProps>()
const emits = defineEmits<DialogRootEmits>()

const forwarded = useForwardProps(props)

const isOpen = useVModel(props, "open", emits, {
  passive: true,
  defaultValue: props.defaultOpen,
})

// Back button/gesture closes the sheet instead of leaving the page. The mobile
// sidebar is the only consumer and it hides its close button, so a swipe or a
// tap on the backdrop were the only ways out — on Android the back gesture is
// the one people reach for first, and it used to take them off the page.
usePanelHistory(isOpen)
</script>

<template>
  <DialogRoot
    data-slot="sheet"
    v-bind="forwarded"
    v-model:open="isOpen"
  >
    <slot />
  </DialogRoot>
</template>
