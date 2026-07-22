<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps<{ class?: HTMLAttributes["class"] }>();
</script>

<template>
  <div
    data-slot="message-header"
    :class="
      cn(
        'cn-message-header flex max-w-full min-w-0 items-center gap-2',
        props.class
      )
    "
  >
    <slot />
  </div>
</template>
