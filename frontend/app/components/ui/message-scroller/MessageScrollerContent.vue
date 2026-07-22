<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { onBeforeUnmount, onMounted, useTemplateRef } from "vue";
import { cn } from "@/lib/utils";
import { useMessageScrollerEngine } from "./context";

const props = defineProps<{
  class?: HTMLAttributes["class"];
  spacerClass?: HTMLAttributes["class"];
}>();

const engine = useMessageScrollerEngine();
const content = useTemplateRef<HTMLElement>("content");
const spacer = useTemplateRef<HTMLElement>("spacer");
let mutationObserver: MutationObserver | null = null;
let resizeObserver: ResizeObserver | null = null;
let resizeFrame: number | null = null;

onMounted(() => {
  engine.setContentElement(content.value);
  engine.setSpacerElement(spacer.value);
  engine.handleContentChange();

  if (content.value && typeof MutationObserver !== "undefined") {
    mutationObserver = new MutationObserver(() => engine.handleContentChange());
    mutationObserver.observe(content.value, { childList: true });
  }
  if (content.value && typeof ResizeObserver !== "undefined") {
    resizeObserver = new ResizeObserver(() => {
      // rAF-debounced: a synchronous handleResize() here re-enters layout and
      // trips "ResizeObserver loop completed with undelivered notifications".
      if (resizeFrame !== null) return;
      resizeFrame = requestAnimationFrame(() => {
        resizeFrame = null;
        engine.handleResize();
      });
    });
    resizeObserver.observe(content.value);
  }
});

onBeforeUnmount(() => {
  mutationObserver?.disconnect();
  resizeObserver?.disconnect();
  if (resizeFrame !== null) cancelAnimationFrame(resizeFrame);
  mutationObserver = null;
  resizeObserver = null;
  engine.setContentElement(null);
  engine.setSpacerElement(null);
});
</script>

<template>
  <div
    ref="content"
    data-slot="message-scroller-content"
    role="log"
    aria-relevant="additions"
    :class="cn('cn-message-scroller-content flex h-max min-h-full flex-col', props.class)"
  >
    <slot />
    <div
      ref="spacer"
      aria-hidden="true"
      data-message-scroller-spacer
      hidden
      :class="props.spacerClass"
    />
  </div>
</template>
