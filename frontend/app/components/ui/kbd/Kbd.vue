<script setup lang="ts">
import { cn } from "@/lib/utils";
import type { HTMLAttributes } from "vue";

const props = defineProps<{
  class?: HTMLAttributes["class"];
}>();
</script>

<template>
  <kbd
    data-slot="kbd"
    :class="
      cn(
        'cn-kbd pointer-events-none inline-flex items-center justify-center select-none',
        'in-data-[variant=default]:bg-primary-foreground/15 dark:in-data-[variant=default]:bg-primary-foreground/8 in-data-[variant=default]:text-primary-foreground',
        props.class
      )
    "
  >
    <slot />
  </kbd>
</template>
