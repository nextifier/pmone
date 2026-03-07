<script setup lang="ts">
import { cn } from "@/lib/utils";
import type { HTMLAttributes } from "vue";

const props = defineProps<{
  class?: HTMLAttributes["class"];
}>();
</script>

<template>
  <kbd
    :class="
      cn(
        'bg-muted text-muted-foreground pointer-events-none inline-flex h-5 w-fit min-w-5 items-center justify-center gap-1 rounded-sm px-1 font-sans text-xs font-medium select-none',
        '[&_svg:not([class*=\'size-\'])]:size-3',
        'in-data-[slot=tooltip-content]:bg-background/20 in-data-[slot=tooltip-content]:text-background dark:in-data-[slot=tooltip-content]:bg-background/10',
        'in-data-[variant=default]:bg-primary-foreground/15 dark:in-data-[variant=default]:bg-primary-foreground/8 in-data-[variant=default]:text-primary-foreground',
        props.class
      )
    "
  >
    <slot />
  </kbd>
</template>
