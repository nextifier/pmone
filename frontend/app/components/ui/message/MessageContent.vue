<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps<{ class?: HTMLAttributes["class"] }>();
</script>

<template>
  <div
    data-slot="message-content"
    :class="
      cn(
        'cn-message-content flex w-full min-w-0 flex-col wrap-break-word has-[[data-slot=bubble][data-variant=ghost]]:gap-0',
        props.class
      )
    "
  >
    <slot />
  </div>
</template>
