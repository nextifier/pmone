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
    aria-label="Next"
    :disabled="!state.props.loop && !state.canNext.value"
    :class="
      cn(
        'group hover:bg-white/15 focus-visible:bg-white/15 focus-visible:ring-2 focus-visible:ring-white/40 absolute top-1/2 right-3 z-30 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/5 text-white backdrop-blur-sm transition-colors focus:outline-hidden disabled:opacity-30 disabled:pointer-events-none sm:size-12 sm:right-5',
        props.class,
      )
    "
    @click="state.next"
  >
    <slot>
      <Icon
        name="lucide:chevron-right"
        class="size-5 opacity-80 transition-opacity group-hover:opacity-100 sm:size-6"
      />
    </slot>
    <span class="sr-only">Next</span>
  </button>
</template>
