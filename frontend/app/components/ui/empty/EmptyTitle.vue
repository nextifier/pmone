<script setup lang="ts">
import { cn } from "@/lib/utils";
import type { HTMLAttributes } from "vue";

const props = defineProps<{
  class?: HTMLAttributes["class"];
}>();
</script>

<template>
  <!-- House style: the empty state is a full-surface message, so its title is a real
       heading rather than shadcn's `cn-empty-title` text-sm. Deliberate deviation
       from upstream — a future cn-* parity resync must not undo it. -->
  <div
    data-slot="empty-title"
    :class="
      cn(
        'cn-empty-title cn-font-heading text-foreground text-xl leading-[1.2]! font-medium tracking-tighter text-balance sm:text-xl',
        props.class
      )
    "
  >
    <slot />
  </div>
</template>
