<script setup lang="ts">
import type { DialogOverlayProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { DialogOverlay } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<DialogOverlayProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <DialogOverlay
    data-slot="sheet-overlay"
    :class="cn('cn-sheet-overlay fixed inset-0 z-50 ease-(--panel-ease) data-open:animate-in data-open:fade-in-0 data-open:duration-(--panel-open-dur) data-closed:animate-out data-closed:fade-out-0 data-closed:duration-(--panel-close-dur) motion-reduce:animate-none', props.class)"
    v-bind="delegatedProps"
  >
    <slot />
  </DialogOverlay>
</template>
