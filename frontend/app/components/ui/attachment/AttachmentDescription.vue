<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps<{ class?: HTMLAttributes["class"] }>();
</script>

<template>
  <span
    data-slot="attachment-description"
    :class="
      cn(
        'block max-w-full min-w-0 truncate text-xs tracking-tight text-muted-foreground group-data-[state=error]/attachment:text-destructive/80',
        props.class
      )
    "
  >
    <slot />
  </span>
</template>
