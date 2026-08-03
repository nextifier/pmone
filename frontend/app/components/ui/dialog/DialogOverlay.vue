<script setup lang="ts">
import { cn } from '@/lib/utils'
import { DialogOverlay, type DialogOverlayProps } from 'reka-ui'
import { computed, type HTMLAttributes } from 'vue'

const props = defineProps<DialogOverlayProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props

  return delegated
})
</script>

<template>
  <DialogOverlay
    data-slot="dialog-overlay"
    v-bind="delegatedProps"
    :class="
      cn(
        'cn-dialog-overlay fixed inset-0 isolate z-50',
        'ease-(--modal-ease) data-open:duration-(--modal-open-dur) data-closed:duration-(--modal-close-dur) motion-reduce:animate-none!',
        props.class,
      )
    "
  >
    <slot />
  </DialogOverlay>
</template>
