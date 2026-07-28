<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps<{
  class?: HTMLAttributes["class"];
}>();
</script>

<template>
  <div
    data-slot="result-content"
    data-result-reveal
    style="--result-i: 4"
    :class="cn('flex w-full max-w-md min-w-0 flex-col items-center gap-3', props.class)"
  >
    <slot />
  </div>
</template>
