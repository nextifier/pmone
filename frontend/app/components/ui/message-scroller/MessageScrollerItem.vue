<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { onBeforeUnmount, watch } from "vue";
import { cn } from "@/lib/utils";
import { useMessageScrollerEngine } from "./context";

const props = withDefaults(
  defineProps<{
    class?: HTMLAttributes["class"];
    messageId?: string;
    scrollAnchor?: boolean;
  }>(),
  { scrollAnchor: false }
);

const engine = useMessageScrollerEngine();
let current: HTMLElement | null = null;

// Stable callback ref: called with the element on mount and `null` on unmount,
// mirroring the React primitive's element registration.
function setEl(el: Element | null): void {
  const previous = current;
  current = (el as HTMLElement) ?? null;
  if (props.messageId) {
    engine.registerMessage(props.messageId, current, previous);
  }
}

watch(
  () => props.messageId,
  (next, prev) => {
    if (prev && prev !== next) {
      engine.registerMessage(prev, null, current);
    }
    if (next && current) {
      engine.registerMessage(next, current, null);
    }
  }
);

onBeforeUnmount(() => {
  if (props.messageId && current) {
    engine.registerMessage(props.messageId, null, current);
  }
  current = null;
});
</script>

<template>
  <div
    :ref="setEl"
    data-slot="message-scroller-item"
    :data-message-id="messageId"
    :data-scroll-anchor="scrollAnchor ? 'true' : 'false'"
    :class="
      cn(
        'min-w-0 shrink-0 [content-visibility:auto] [contain-intrinsic-size:auto_4rem]',
        props.class
      )
    "
  >
    <slot />
  </div>
</template>
