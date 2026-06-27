<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = withDefaults(
  defineProps<{ class?: HTMLAttributes["class"]; align?: "start" | "end" }>(),
  { align: "start" }
);
</script>

<template>
  <div
    data-slot="message"
    :data-align="align"
    :class="
      cn(
        'group/message relative flex w-full min-w-0 gap-2 data-[align=end]:flex-row-reverse',
        props.class
      )
    "
  >
    <slot />
  </div>
</template>
