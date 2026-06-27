<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps<{ class?: HTMLAttributes["class"] }>();
</script>

<template>
  <div
    data-slot="attachment-group"
    :class="
      cn(
        'no-scrollbar flex min-w-0 snap-x snap-mandatory gap-2 overflow-x-auto overscroll-x-contain scroll-fade-x *:data-[slot=attachment]:flex-none *:data-[slot=attachment]:snap-start',
        props.class
      )
    "
  >
    <slot />
  </div>
</template>
