<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

interface Props {
  class?: HTMLAttributes['class']
}

const props = defineProps<Props>()
</script>

<template>
  <div
    :class="cn(
      'sticky bottom-0 flex flex-col-reverse gap-2 border-t bg-background px-4 py-4 sm:flex-row sm:justify-end',
      props.class
    )"
  >
    <slot />
  </div>
</template>
