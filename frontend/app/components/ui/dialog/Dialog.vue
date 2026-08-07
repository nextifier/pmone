<script setup lang="ts">
import { DialogRoot, type DialogRootEmits, type DialogRootProps, useForwardProps } from 'reka-ui'
import { useVModel } from '@vueuse/core'
import { usePanelHistory } from '@/components/ui/panel-history'

const props = defineProps<DialogRootProps>()
const emits = defineEmits<DialogRootEmits>()

const forwarded = useForwardProps(props)

const isOpen = useVModel(props, 'open', emits, {
  passive: true,
  defaultValue: props.defaultOpen,
})

// Back button/gesture closes the dialog instead of navigating away.
//
// This used to be a private copy of the same idea: its own `popstate` listener
// and its own `history.back()`. Two independent listeners meant a dialog opened
// from inside a drawer took the drawer down with it on close — the drawer heard
// the rewind the dialog had asked for and read it as a back gesture. One shared
// stack keeps each press closing exactly one panel.
usePanelHistory(isOpen)
</script>

<template>
  <DialogRoot
    data-slot="dialog"
    v-bind="forwarded"
    v-model:open="isOpen"
  >
    <slot />
  </DialogRoot>
</template>
