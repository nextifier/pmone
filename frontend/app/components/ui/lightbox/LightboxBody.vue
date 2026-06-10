<script setup lang="ts">
import { useNuxtApp } from "#app";
import { cn } from "@/lib/utils";
import type { EmblaCarouselType } from "embla-carousel";
import emblaCarouselVue from "embla-carousel-vue";
import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
  type HTMLAttributes,
} from "vue";
import LightboxCaption from "./LightboxCaption.vue";
import LightboxClose from "./LightboxClose.vue";
import LightboxCounter from "./LightboxCounter.vue";
import LightboxDownload from "./LightboxDownload.vue";
import LightboxFullscreen from "./LightboxFullscreen.vue";
import LightboxImage from "./LightboxImage.vue";
import LightboxNext from "./LightboxNext.vue";
import LightboxPrevious from "./LightboxPrevious.vue";
import LightboxShare from "./LightboxShare.vue";
import LightboxThumbnails from "./LightboxThumbnails.vue";
import LightboxVideo from "./LightboxVideo.vue";
import type { LightboxEmblaPlugin, LightboxImageSource } from "./interface";
import {
  createAutoplayPlugin,
  isVideoItem,
  pickCaption,
  useLightbox,
} from "./useLightbox";

const props = defineProps<{
  showClose?: boolean;
  showCounter?: boolean;
  showDownload?: boolean;
  showNavButtons?: boolean;
  showThumbnails?: boolean;
  showCaption?: boolean;
  showFullscreen?: boolean;
  showShare?: boolean;
  class?: HTMLAttributes["class"];
}>();

const state = useLightbox();
const { items, close, props: lightboxProps } = state;
const { $wheelGesturesPlugin } = useNuxtApp();

// Only reserve caption space when the current item actually has one, so
// caption-less photos give that room back to the image.
const currentCaption = computed(() => {
  const item = state.current.value;
  return item ? pickCaption(item) : "";
});

const autoplayDelay = computed(() =>
  typeof lightboxProps.autoplay === "number" ? lightboxProps.autoplay : 0,
);

const autoplayPlugin = autoplayDelay.value
  ? createAutoplayPlugin(autoplayDelay.value)
  : null;

const wheelPlugin: LightboxEmblaPlugin | null =
  typeof $wheelGesturesPlugin === "function"
    ? ($wheelGesturesPlugin as () => LightboxEmblaPlugin)()
    : null;

const emblaPlugins: LightboxEmblaPlugin[] = [
  ...(lightboxProps.emblaPlugins || []),
  ...(wheelPlugin ? [wheelPlugin] : []),
  ...(autoplayPlugin ? [autoplayPlugin as unknown as LightboxEmblaPlugin] : []),
];

const [mainRef, mainApi] = emblaCarouselVue(
  {
    loop: lightboxProps.loop ?? true,
    startIndex: state.index.value,
    align: "center",
    containScroll: false,
    ...(lightboxProps.emblaOpts || {}),
  },
  emblaPlugins,
);

const autoplayProgress = ref(0);
let autoplayTimer: number | null = null;

function startAutoplayTimer() {
  stopAutoplayTimer();
  if (!autoplayDelay.value || !state.open.value) {
    return;
  }
  const startedAt = performance.now();
  const delay = autoplayDelay.value;
  autoplayTimer = window.setInterval(() => {
    const elapsed = performance.now() - startedAt;
    autoplayProgress.value = Math.min(1, (elapsed % delay) / delay);
  }, 60);
}

function stopAutoplayTimer() {
  if (autoplayTimer !== null) {
    window.clearInterval(autoplayTimer);
    autoplayTimer = null;
  }
  autoplayProgress.value = 0;
}

function syncFromMain(api: EmblaCarouselType) {
  const i = api.selectedScrollSnap();
  if (i !== state.index.value) {
    state.index.value = i;
  }
  state.thumbsApi.value?.scrollTo(i);
  state.setCanScroll(api);
  state.emitChange(i);
  state.resetZoom();
  if (autoplayPlugin) {
    const item = items.value[i];
    if (item && isVideoItem(item)) {
      autoplayPlugin.stop();
      stopAutoplayTimer();
    } else if (state.open.value) {
      autoplayPlugin.play();
      startAutoplayTimer();
    }
  }
}

onMounted(() => {
  if (mainApi.value) {
    state.mainApi.value = mainApi.value;
    mainApi.value.on("init", syncFromMain);
    mainApi.value.on("select", syncFromMain);
    mainApi.value.on("reInit", syncFromMain);
    state.setCanScroll(mainApi.value);
    mainApi.value.scrollTo(state.index.value, true);
  }
});

onBeforeUnmount(() => {
  if (mainApi.value) {
    mainApi.value.off("init", syncFromMain);
    mainApi.value.off("select", syncFromMain);
    mainApi.value.off("reInit", syncFromMain);
  }
  state.mainApi.value = null;
  if (autoplayPlugin) {
    autoplayPlugin.stop();
  }
  stopAutoplayTimer();
});

watch(
  () => state.index.value,
  (i) => {
    if (mainApi.value && mainApi.value.selectedScrollSnap() !== i) {
      mainApi.value.scrollTo(i);
    }
  },
);

watch(
  () => state.open.value,
  (open) => {
    if (open) {
      nextTick(() => {
        mainApi.value?.reInit();
        mainApi.value?.scrollTo(state.index.value, true);
        if (autoplayPlugin) {
          const item = items.value[state.index.value];
          if (item && !isVideoItem(item)) {
            autoplayPlugin.play();
            startAutoplayTimer();
          }
        }
      });
    } else if (autoplayPlugin) {
      autoplayPlugin.stop();
      stopAutoplayTimer();
    }
  },
);

function onSlideBackdropClick(event: MouseEvent) {
  if (
    event.target === event.currentTarget &&
    lightboxProps.closeOnBackdropClick
  ) {
    close();
  }
}

function onViewportBackdropClick(event: MouseEvent) {
  if (
    event.target === event.currentTarget &&
    lightboxProps.closeOnBackdropClick
  ) {
    close();
  }
}

// Pull-to-close gesture (vertical swipe).
const dragY = ref(0);
const dragging = ref(false);
let startY = 0;
const dragOpacity = computed(() => {
  if (!dragging.value) {
    return 1;
  }
  const distance = Math.abs(dragY.value);
  return Math.max(0.3, 1 - distance / 400);
});

function onPointerDown(event: PointerEvent) {
  if (!lightboxProps.swipeToClose) {
    return;
  }
  if (event.pointerType !== "touch") {
    return;
  }
  if (state.isZoomed.value) {
    return;
  }
  startY = event.clientY;
  dragY.value = 0;
  dragging.value = true;
}

function onPointerMove(event: PointerEvent) {
  if (!dragging.value) {
    return;
  }
  dragY.value = event.clientY - startY;
}

function onPointerEnd() {
  if (!dragging.value) {
    return;
  }
  const shouldClose = Math.abs(dragY.value) > 120;
  dragging.value = false;
  if (shouldClose) {
    close();
  }
  dragY.value = 0;
}

const dragStyle = computed(() => {
  if (!dragging.value && dragY.value === 0) {
    return {};
  }
  return {
    transform: `translateY(${dragY.value}px)`,
    transition: dragging.value ? "none" : "transform 200ms ease-out",
  };
});

const overlayStyle = computed(() => {
  // Only force inline opacity while a pull-to-close drag is in progress, so the
  // chrome show/hide transition (which animates opacity via class) isn't
  // overridden by an always-present inline opacity when idle.
  if (!dragging.value && dragY.value === 0) {
    return {};
  }
  return {
    opacity: dragOpacity.value,
    transition: dragging.value ? "none" : "opacity 200ms ease-out",
  };
});

const controlsVisible = computed(() => state.controlsVisible.value);
// Stringified for the [data-open] CSS hooks (Vue drops `false` boolean attrs).
const dataOpen = computed(() => (controlsVisible.value ? "true" : "false"));

const autoplayProgressVisible = computed(
  () => !!autoplayDelay.value && state.open.value,
);
</script>

<template>
  <div
    :class="
      cn(
        'flex h-full w-full flex-col pb-[env(safe-area-inset-bottom)]',
        props.class,
      )
    "
    :style="dragStyle"
    @pointerdown="onPointerDown"
    @pointermove="onPointerMove"
    @pointerup="onPointerEnd"
    @pointercancel="onPointerEnd"
  >
    <div
      v-if="autoplayProgressVisible"
      class="pointer-events-none absolute inset-x-0 top-0 z-30 h-0.5 bg-white/10"
    >
      <div
        class="h-full bg-white/70"
        :style="{
          width: `${autoplayProgress * 100}%`,
          transition: 'width 80ms linear',
        }"
      />
    </div>

    <div
      class="t-panel-slide relative z-20 flex items-center justify-between gap-2 px-3 pt-3 sm:px-5 sm:pt-4 lg:absolute lg:inset-x-0 lg:top-0 lg:bg-linear-to-b lg:from-black/55 lg:via-black/15 lg:to-transparent lg:pb-10"
      :data-open="dataOpen"
      :style="[overlayStyle, { '--panel-translate-y': '-0.75rem' }]"
    >
      <div class="flex items-center gap-2">
        <slot name="counter">
          <LightboxCounter v-if="showCounter && state.isMultiple.value" />
        </slot>
      </div>
      <div class="flex items-center gap-1">
        <slot name="actions">
          <LightboxShare v-if="showShare" />
          <LightboxFullscreen v-if="showFullscreen" />
          <LightboxDownload v-if="showDownload" />
          <LightboxClose v-if="showClose" />
        </slot>
      </div>
    </div>

    <div
      class="t-pt relative flex flex-1 items-center justify-center overflow-hidden"
      :class="controlsVisible ? 'lg:pt-2' : ''"
      :style="overlayStyle"
      @click="onViewportBackdropClick"
    >
      <div ref="mainRef" class="size-full overflow-hidden">
        <div class="flex h-full">
          <div
            v-for="(item, i) in items"
            :key="i"
            class="relative flex h-full min-w-0 shrink-0 grow-0 basis-full items-center justify-center px-1 sm:px-12"
            @click="onSlideBackdropClick"
          >
            <LightboxVideo v-if="isVideoItem(item)" :item :index="i" />
            <LightboxImage
              v-else
              :item="item as LightboxImageSource"
              :index="i"
              :full-key="lightboxProps.fullKey"
            />
          </div>
        </div>
      </div>

      <!-- lg+ nav: edge-aligned, vertically centred over the image so the main
           image can use the full height (mobile/tablet keep the bottom row). -->
      <div
        v-if="showNavButtons && state.isMultiple.value"
        class="pointer-events-none absolute inset-y-0 right-5 left-5 z-20 hidden items-center justify-between lg:flex"
        :style="overlayStyle"
      >
        <span class="t-panel-slide" :data-open="dataOpen" :style="{ '--panel-translate-y': '0px' }">
          <slot name="previous">
            <LightboxPrevious />
          </slot>
        </span>
        <span class="t-panel-slide" :data-open="dataOpen" :style="{ '--panel-translate-y': '0px' }">
          <slot name="next">
            <LightboxNext />
          </slot>
        </span>
      </div>
    </div>

    <!-- Bottom cluster (caption + thumbnails + mobile nav), each with its own
         dedicated space. Toggling the chrome collapses the cluster's height
         (t-collapse → the image grows into the freed space) while its contents
         fade + slide out (t-panel-slide). -->
    <div class="t-collapse" :data-open="dataOpen">
      <div>
        <div
          class="t-panel-slide"
          :data-open="dataOpen"
          :style="[overlayStyle, { '--panel-translate-y': '1rem' }]"
        >
          <div v-if="showCaption && currentCaption" class="px-4 pt-2 sm:pt-3">
            <slot name="caption">
              <LightboxCaption />
            </slot>
          </div>

          <div
            v-if="showNavButtons && state.isMultiple.value"
            class="flex items-center justify-center gap-x-2 px-4 pt-3 lg:hidden"
          >
            <slot name="previous">
              <LightboxPrevious />
            </slot>
            <slot name="next">
              <LightboxNext />
            </slot>
          </div>

          <div v-if="showThumbnails" class="px-2 pt-2 pb-3 sm:pb-4">
            <slot name="thumbnails">
              <LightboxThumbnails />
            </slot>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
