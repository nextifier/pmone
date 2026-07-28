<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps<{
  class?: HTMLAttributes["class"];
}>();
</script>

<template>
  <div
    data-slot="result-actions"
    data-result-reveal
    style="--result-i: 5"
    :class="
      cn(
        'flex w-full flex-col items-center justify-center gap-2 sm:w-auto sm:flex-row',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
