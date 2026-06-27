<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps<{ class?: HTMLAttributes["class"] }>();
</script>

<template>
  <div
    data-slot="message-content"
    :class="cn('flex w-full min-w-0 flex-col gap-1 wrap-break-word', props.class)"
  >
    <slot />
  </div>
</template>
