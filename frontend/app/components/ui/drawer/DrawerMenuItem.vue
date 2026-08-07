<script lang="ts" setup>
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = withDefaults(
  defineProps<{
    class?: HTMLAttributes["class"];
    variant?: "default" | "destructive";
    disabled?: boolean;
  }>(),
  { variant: "default", disabled: false }
);
</script>

<template>
  <!-- Classes copied verbatim from coss drawer.tsx (DrawerMenuItem). -->
  <button
    type="button"
    data-slot="drawer-menu-item"
    :data-variant="variant"
    :disabled="disabled"
    :class="
      cn(
        `flex min-h-9 w-full cursor-default select-none items-center gap-2 rounded-sm px-2 py-1 text-sm text-foreground outline-none hover:bg-accent hover:text-accent-foreground disabled:pointer-events-none disabled:opacity-64 data-[variant=destructive]:text-destructive-foreground sm:min-h-8 [&>svg:not([class*='opacity-'])]:opacity-80 [&>svg:not([class*='size-'])]:size-4.5 sm:[&>svg:not([class*='size-'])]:size-4 [&>svg]:pointer-events-none [&>svg]:-mx-0.5 [&>svg]:shrink-0`,
        props.class
      )
    "
  >
    <slot />
  </button>
</template>
