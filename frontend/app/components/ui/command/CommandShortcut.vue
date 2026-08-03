<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <span
    data-slot="command-shortcut"
    :class="
      cn(
        // The stylesheet brightens the shortcut off `group-data-selected/command-item`,
        // the attribute cmdk sets. Reka marks the active option with `data-highlighted`,
        // so mirror that one declaration here — same as `CommandItem` does.
        'cn-command-shortcut group-data-highlighted/command-item:text-foreground',
        props.class
      )
    "
  >
    <slot />
  </span>
</template>
