<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { onBeforeUnmount, onMounted, useTemplateRef, watch } from "vue";
import { cn } from "@/lib/utils";
import { useMessageScrollerEngine } from "./context";

const props = withDefaults(
  defineProps<{
    class?: HTMLAttributes["class"];
    ariaLabel?: string;
    preserveScrollOnPrepend?: boolean;
  }>(),
  { preserveScrollOnPrepend: true }
);

const engine = useMessageScrollerEngine();
const el = useTemplateRef<HTMLElement>("viewport");
let observer: ResizeObserver | null = null;
let resizeFrame: number | null = null;

watch(
  () => props.preserveScrollOnPrepend,
  (value) => engine.setPreserveScrollOnPrepend(value),
  { immediate: true }
);

onMounted(() => {
  engine.setViewportElement(el.value);
  if (el.value && typeof ResizeObserver !== "undefined") {
    observer = new ResizeObserver(() => {
      // rAF-debounced: a synchronous handleResize() here re-enters layout and
      // trips "ResizeObserver loop completed with undelivered notifications".
      if (resizeFrame !== null) return;
      resizeFrame = requestAnimationFrame(() => {
        resizeFrame = null;
        engine.handleResize();
      });
    });
    observer.observe(el.value);
  }
});

onBeforeUnmount(() => {
  observer?.disconnect();
  if (resizeFrame !== null) cancelAnimationFrame(resizeFrame);
  observer = null;
  engine.setViewportElement(null);
});

function onScroll(): void {
  engine.syncAfterScroll();
}
function onWheel(): void {
  engine.userScrollIntent();
}
function onTouchMove(): void {
  engine.userScrollIntent();
}
function onKeyDown(event: KeyboardEvent): void {
  if (engine.isIntentKey(event.key)) {
    engine.userScrollIntent();
  }
}
</script>

<template>
  <div
    ref="viewport"
    data-slot="message-scroller-viewport"
    role="region"
    :aria-label="ariaLabel ?? 'Messages'"
    :tabindex="0"
    :class="
      cn(
        'cn-message-scroller-viewport size-full min-h-0 min-w-0 overflow-y-auto overscroll-contain scroll-fade-b [contain:content]',
        props.class
      )
    "
    @scroll="onScroll"
    @wheel="onWheel"
    @touchmove="onTouchMove"
    @keydown="onKeyDown"
  >
    <slot />
  </div>
</template>
