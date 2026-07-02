<script setup lang="ts">
import { type HTMLAttributes, computed } from "vue";
import {
  ScrollAreaCorner,
  ScrollAreaRoot,
  type ScrollAreaRootProps,
  ScrollAreaViewport,
} from "reka-ui";
import ScrollBar from "./ScrollBar.vue";
import { cn } from "@/lib/utils";

const props = defineProps<
  ScrollAreaRootProps & {
    class?: HTMLAttributes["class"];
    viewportClass?: HTMLAttributes["class"];
  }
>();

const delegatedProps = computed(() => {
  const { class: _, viewportClass: __, ...delegated } = props;

  return delegated;
});
</script>

<template>
  <ScrollAreaRoot
    data-slot="scroll-area"
    v-bind="delegatedProps"
    :class="cn('cn-scroll-area relative', props.class)"
  >
    <ScrollAreaViewport
      data-slot="scroll-area-viewport"
      :class="cn('cn-scroll-area-viewport size-full rounded-[inherit] transition-[color,box-shadow] outline-none focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-1', props.viewportClass)"
    >
      <slot />
    </ScrollAreaViewport>
    <ScrollBar />
    <ScrollAreaCorner />
  </ScrollAreaRoot>
</template>
