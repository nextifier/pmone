<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps<{ class?: HTMLAttributes["class"] }>();
</script>

<template>
  <span
    data-slot="marker-content"
    :class="cn('min-w-0 wrap-break-word', props.class)"
  >
    <slot />
  </span>
</template>
