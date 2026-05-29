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
    v-bind="delegatedProps"
    :class="cn('relative overflow-hidden', props.class)"
  >
    <ScrollAreaViewport
      :class="cn('h-full w-full rounded-[inherit] outline-hidden', props.viewportClass)"
    >
      <slot />
    </ScrollAreaViewport>
    <ScrollBar />
    <ScrollAreaCorner />
  </ScrollAreaRoot>
</template>
