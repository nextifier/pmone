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
        'flex w-full min-w-0 flex-col gap-1 wrap-break-word group-data-[align=end]/message:items-end has-[[data-slot=bubble][data-variant=ghost]]:gap-0',
        props.class
      )
    "
  >
    <slot />
  </div>
</template>
