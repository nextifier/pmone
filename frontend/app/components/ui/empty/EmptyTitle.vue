<script setup lang="ts">
import { cn } from "@/lib/utils";
import type { HTMLAttributes } from "vue";

const props = defineProps<{
  class?: HTMLAttributes["class"];
}>();
</script>

<template>
  <div data-slot="empty-title" :class="cn('cn-empty-title cn-font-heading', props.class)">
    <slot />
  </div>
</template>
