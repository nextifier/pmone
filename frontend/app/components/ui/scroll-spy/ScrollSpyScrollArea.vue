<script setup lang="ts">
import { provide, ref } from "vue";
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";
import { SCROLL_SPY_VIEWPORT } from "./useScrollSpy";

const props = defineProps<{ class?: HTMLAttributes["class"] }>();

const viewportRef = ref<HTMLElement | null>(null);
provide(SCROLL_SPY_VIEWPORT, viewportRef);
</script>

<template>
  <div
    ref="viewportRef"
    data-slot="scroll-spy-scroll-area"
    :class="
      cn(
        'relative ms-px min-h-0 [scrollbar-width:none] overflow-auto mask-[linear-gradient(to_bottom,transparent,white_16px,white_calc(100%-16px),transparent)] py-3 text-sm',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
