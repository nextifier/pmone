<script setup lang="ts">
import { DialogRoot, type DialogRootEmits, type DialogRootProps, useForwardProps } from 'reka-ui'
import { useVModel } from '@vueuse/core'
import { onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps<DialogRootProps>()
const emits = defineEmits<DialogRootEmits>()

const forwarded = useForwardProps(props)

const isOpen = useVModel(props, 'open', emits, {
  passive: true,
  defaultValue: props.defaultOpen,
})

// Back button/gesture closes dialog instead of navigating away
const pushedHistoryState = ref(false)

const onPopState = () => {
  pushedHistoryState.value = false
  isOpen.value = false
}

watch(isOpen, (newVal, oldVal) => {
  if (newVal && !oldVal) {
    window.history.pushState({ dialogOpen: true }, '')
    pushedHistoryState.value = true
    window.addEventListener('popstate', onPopState, { once: true })
  } else if (!newVal && oldVal && pushedHistoryState.value) {
    pushedHistoryState.value = false
    window.removeEventListener('popstate', onPopState)
    window.history.back()
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('popstate', onPopState)
})
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
