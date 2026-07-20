<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps<{ class?: HTMLAttributes["class"] }>();
</script>

<template>
  <div
    data-slot="message-avatar"
    :class="
      cn(
        'flex size-8 w-fit shrink-0 items-center justify-center self-end overflow-hidden rounded-full bg-muted group-has-[[data-slot=message-footer]]/message:mb-6',
        props.class
      )
    "
  >
    <slot />
  </div>
</template>
