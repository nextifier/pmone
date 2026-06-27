<script setup lang="ts">
import { onBeforeUnmount, onMounted, provide } from "vue";
import type { MessageScrollerDefaultScrollPosition } from "./engine";
import { createMessageScrollerEngine } from "./engine";
import { MessageScrollerContextKey } from "./context";

const props = withDefaults(
  defineProps<{
    autoScroll?: boolean;
    defaultScrollPosition?: MessageScrollerDefaultScrollPosition;
    scrollEdgeThreshold?: number;
    scrollPreviousItemPeek?: number;
    scrollMargin?: number;
  }>(),
  {
    autoScroll: false,
    defaultScrollPosition: "end",
  }
);

const engine = createMessageScrollerEngine({
  autoScroll: props.autoScroll,
  defaultScrollPosition: props.defaultScrollPosition,
  scrollEdgeThreshold: props.scrollEdgeThreshold,
  scrollPreviousItemPeek: props.scrollPreviousItemPeek,
  scrollMargin: props.scrollMargin,
});

provide(MessageScrollerContextKey, engine);

onMounted(() => {
  engine.initialize();
});

onBeforeUnmount(() => {
  engine.destroy();
});
</script>

<template>
  <slot />
</template>
