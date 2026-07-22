<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps<{ class?: HTMLAttributes["class"] }>();
</script>

<template>
  <div
    data-slot="message-avatar"
    :class="
      cn(
        'cn-message-avatar flex size-8 shrink-0 items-center justify-center self-end overflow-hidden rounded-full bg-muted',
        props.class
      )
    "
  >
    <slot />
  </div>
</template>
