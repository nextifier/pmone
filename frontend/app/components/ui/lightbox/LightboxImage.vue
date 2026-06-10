<script setup lang="ts">
import { cn } from "@/lib/utils";
import { computed, ref, watch, type HTMLAttributes } from "vue";
import { Spinner } from "@/components/ui/spinner";
import type { LightboxImageSource, LightboxThumbnailKey } from "./interface";
import { pickAlt, pickImageSrc, useLightbox } from "./useLightbox";

const props = defineProps<{
  item: LightboxImageSource;
  index: number;
  fullKey?: LightboxThumbnailKey;
  class?: HTMLAttributes["class"];
}>();

const state = useLightbox();

const src = computed(() =>
  pickImageSrc(props.item, props.fullKey || state.props.fullKey || "lg"),
);

const alt = computed(() => pickAlt(props.item, state.props.alt));

const isActive = computed(() => state.index.value === props.index);
const isNearby = computed(() => Math.abs(state.index.value - props.index) <= 1);
const shouldRender = computed(() => isActive.value || isNearby.value);

const status = ref<"loading" | "ready" | "error">("loading");

watch(src, () => {
  status.value = "loading";
});

function onLoad() {
  status.value = "ready";
}

function onError() {
  status.value = "error";
}

function onClick(event: MouseEvent) {
  event.stopPropagation();
  if (!isActive.value) {
    return;
  }
  // Zoomed → click zooms back out; otherwise toggle the lightbox controls
  // (top bar, caption, thumbnails, nav) so the image can use the full space.
  if (isZoomed.value) {
    state.resetZoom();
    return;
  }
  state.toggleControls();
}

const isZoomed = computed(() => state.isZoomed.value && isActive.value);
</script>

<template>
  <div
    v-if="shouldRender"
    :class="
      cn(
        'relative flex h-full w-full items-center justify-center pointer-events-none',
        props.class,
      )
    "
    @click.stop
  >
    <Spinner
      v-if="status === 'loading'"
      class="absolute size-8 text-white/70"
    />

    <div
      v-if="status === 'error'"
      class="text-white/70 absolute inset-0 flex flex-col items-center justify-center gap-2 text-sm tracking-tight"
    >
      <Icon name="lucide:image-off" class="size-12 opacity-60" />
      <span>Image unavailable</span>
    </div>

    <img
      :src="src"
      :alt="alt"
      :loading="isActive ? 'eager' : 'lazy'"
      decoding="async"
      draggable="false"
      v-bind="state.props.imageProps"
      :class="
        cn(
          'pointer-events-auto max-h-full max-w-full select-none object-contain transition-[opacity,transform] duration-300 ease-out',
          status === 'ready'
            ? 'opacity-100 scale-100'
            : 'opacity-0 scale-[0.98]',
          isZoomed && 'cursor-zoom-out scale-[1.6]',
          !isZoomed && isActive && 'cursor-pointer',
        )
      "
      @load="onLoad"
      @error="onError"
      @click="onClick"
    />
  </div>
</template>
