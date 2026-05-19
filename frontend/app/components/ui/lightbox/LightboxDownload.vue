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
    type="button"
    aria-label="Download"
    :class="
      cn(
        'group hover:bg-white/15 focus-visible:bg-white/15 focus-visible:ring-2 focus-visible:ring-white/40 flex size-10 items-center justify-center rounded-full text-white transition-colors focus:outline-hidden disabled:pointer-events-none',
        props.class,
      )
    "
    @click="state.download"
  >
    <slot>
      <Icon
        name="lucide:download"
        class="size-5 opacity-80 transition-opacity group-hover:opacity-100"
      />
    </slot>
    <span class="sr-only">Download</span>
  </button>
</template>
