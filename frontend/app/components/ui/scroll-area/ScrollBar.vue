<script setup lang="ts">
import { type HTMLAttributes, computed } from "vue";
import {
  ScrollAreaScrollbar,
  type ScrollAreaScrollbarProps,
  ScrollAreaThumb,
} from "reka-ui";
import { cn } from "@/lib/utils";

const props = withDefaults(
  defineProps<ScrollAreaScrollbarProps & { class?: HTMLAttributes["class"] }>(),
  {
    orientation: "vertical",
  },
);

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;

  return delegated;
});
</script>

<template>
  <ScrollAreaScrollbar
    data-slot="scroll-area-scrollbar"
    :data-orientation="orientation"
    v-bind="delegatedProps"
    :class="cn('cn-scroll-area-scrollbar flex touch-none p-px transition-colors select-none', props.class)"
  >
    <ScrollAreaThumb
      data-slot="scroll-area-thumb"
      class="cn-scroll-area-thumb relative flex-1 bg-border"
    />
  </ScrollAreaScrollbar>
</template>
