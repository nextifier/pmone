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
        'group flex size-10 items-center justify-center rounded-full bg-white/5 text-white backdrop-blur-sm transition-colors hover:bg-white/15 focus:outline-hidden focus-visible:bg-white/15 focus-visible:ring-2 focus-visible:ring-white/40 active:scale-98 disabled:pointer-events-none disabled:opacity-30 sm:size-12',
        props.class,
      )
    "
    @click="state.next"
  >
    <slot>
      <Icon
        name="material-symbols:chevron-right"
        class="size-6 opacity-80 transition-opacity group-hover:opacity-100 sm:size-7"
      />
    </slot>
    <span class="sr-only">Next</span>
  </button>
</template>
