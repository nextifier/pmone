<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { DialogClose } from 'reka-ui'
import { cn } from '@/lib/utils'
import { buttonVariants } from '@/components/ui/button'

const props = withDefaults(
  defineProps<{ class?: HTMLAttributes['class']; showCloseButton?: boolean }>(),
  { showCloseButton: false },
)
</script>

<template>
  <div
    data-slot="dialog-footer"
    :class="cn('cn-dialog-footer flex flex-col-reverse gap-2 sm:flex-row sm:justify-end', props.class)"
  >
    <DialogClose
      v-if="props.showCloseButton"
      data-slot="dialog-close"
      :class="buttonVariants({ variant: 'outline' })"
    >
      Close
    </DialogClose>
    <slot />
  </div>
</template>
