<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps<{
  class?: HTMLAttributes["class"];
}>();
</script>

<template>
  <p
    data-slot="result-description"
    data-result-reveal
    style="--result-i: 2"
    :class="
      cn(
        'text-muted-foreground max-w-md text-sm tracking-tight text-balance sm:text-base [&>a]:underline [&>a]:underline-offset-4 [&>a:hover]:text-primary',
        props.class,
      )
    "
  >
    <slot />
  </p>
</template>
