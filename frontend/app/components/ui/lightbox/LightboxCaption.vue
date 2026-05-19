<script setup lang="ts">
import { cn } from "@/lib/utils";
import { computed, type HTMLAttributes } from "vue";
import { pickCaption, useLightbox } from "./useLightbox";

const props = defineProps<{
  class?: HTMLAttributes["class"];
}>();

const state = useLightbox();

const caption = computed(() => {
  const item = state.current.value;
  if (!item) {
    return "";
  }
  return pickCaption(item);
});
</script>

<template>
  <div
    v-if="caption"
    data-slot="lightbox-caption"
    :class="
      cn(
        'pointer-events-none mx-auto max-w-3xl px-4 text-center text-sm tracking-tight text-white/85 sm:text-base',
        props.class,
      )
    "
  >
    <slot :caption="caption" :item="state.current.value">
      {{ caption }}
    </slot>
  </div>
</template>
