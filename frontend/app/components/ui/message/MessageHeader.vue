<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps<{ class?: HTMLAttributes["class"] }>();
</script>

<template>
  <div
    data-slot="message-header"
    :class="
      cn(
        'flex max-w-full min-w-0 items-center gap-2 text-sm tracking-tight text-muted-foreground',
        props.class
      )
    "
  >
    <slot />
  </div>
</template>
