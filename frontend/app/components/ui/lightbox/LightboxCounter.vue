<script setup lang="ts">
import { cn } from "@/lib/utils";
import type { HTMLAttributes } from "vue";
import { useLightbox } from "./useLightbox";

const props = defineProps<{
  class?: HTMLAttributes["class"];
}>();

const state = useLightbox();
</script>

<template>
  <span
    data-slot="lightbox-counter"
    aria-live="polite"
    :class="
      cn(
        'rounded-full bg-white/10 px-3 py-1 text-xs font-medium tracking-tight text-white backdrop-blur-sm sm:text-sm',
        props.class,
      )
    "
  >
    <slot
      :label="state.counterLabel.value"
      :index="state.index.value"
      :total="state.items.value.length"
    >
      {{ state.counterLabel.value }}
    </slot>
  </span>
</template>
