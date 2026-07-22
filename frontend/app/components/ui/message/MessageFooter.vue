<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps<{ class?: HTMLAttributes["class"] }>();
</script>

<template>
  <div
    data-slot="message-footer"
    :class="
      cn(
        'cn-message-footer flex max-w-full min-w-0 items-center gap-2 group-data-[align=end]/message:justify-end',
        props.class
      )
    "
  >
    <slot />
  </div>
</template>
