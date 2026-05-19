<script setup lang="ts">
import { cn } from "@/lib/utils";
import { ref, type HTMLAttributes, watch } from "vue";
import type { LightboxVideoSource } from "./interface";
import { useLightbox } from "./useLightbox";

const props = defineProps<{
  item: LightboxVideoSource;
  index: number;
  class?: HTMLAttributes["class"];
}>();

const state = useLightbox();
const videoRef = ref<HTMLVideoElement | null>(null);

watch(
  [() => state.index.value, () => state.open.value],
  ([currentIndex, isOpen]) => {
    const el = videoRef.value;
    if (!el) {
      return;
    }
    if (!isOpen || currentIndex !== props.index) {
      el.pause();
      el.currentTime = 0;
    }
  },
);
</script>

<template>
  <video
    ref="videoRef"
    :src="props.item.src"
    :poster="props.item.poster"
    :muted="props.item.muted"
    :autoplay="props.item.autoplay"
    controls
    playsinline
    preload="metadata"
    :class="
      cn(
        'max-h-full max-w-full bg-black select-none pointer-events-auto',
        props.class,
      )
    "
    @click.stop
  />
</template>
