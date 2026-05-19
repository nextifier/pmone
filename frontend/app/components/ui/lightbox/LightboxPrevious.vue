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
  <button
    v-show="state.items.value.length > 1"
    type="button"
    aria-label="Previous"
    :disabled="!state.props.loop && !state.canPrev.value"
    :class="
      cn(
        'group hover:bg-white/15 focus-visible:bg-white/15 focus-visible:ring-2 focus-visible:ring-white/40 absolute top-1/2 left-3 z-30 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/5 text-white backdrop-blur-sm transition-colors focus:outline-hidden disabled:opacity-30 disabled:pointer-events-none sm:size-12 sm:left-5',
        props.class,
      )
    "
    @click="state.prev"
  >
    <slot>
      <Icon
        name="lucide:chevron-left"
        class="size-5 opacity-80 transition-opacity group-hover:opacity-100 sm:size-6"
      />
    </slot>
    <span class="sr-only">Previous</span>
  </button>
</template>
