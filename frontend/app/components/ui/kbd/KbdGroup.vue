<script setup lang="ts">
import { cn } from "@/lib/utils";
import type { HTMLAttributes } from "vue";

const props = defineProps<{
  class?: HTMLAttributes["class"];
}>();
</script>

<template>
  <kbd data-slot="kbd-group" :class="cn('hidden items-center gap-1 sm:inline-flex', props.class)">
    <slot />
  </kbd>
</template>
