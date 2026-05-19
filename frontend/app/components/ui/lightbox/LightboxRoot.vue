<script setup lang="ts">
import { DialogRoot } from "reka-ui";
import type { LightboxEmits, LightboxProps } from "./interface";
import { useProvideLightbox } from "./useLightbox";

const props = withDefaults(defineProps<LightboxProps>(), {
  loop: true,
  showThumbnails: true,
  showCounter: true,
  showDownload: true,
  showClose: true,
  showNavButtons: true,
  showCaption: true,
  showFullscreen: false,
  showShare: false,
  closeOnBackdropClick: true,
  keyboard: true,
  autoplay: false,
  zoomable: true,
  swipeToClose: false,
  thumbnailKey: "sm",
  fullKey: "lg",
  gridClass: "grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6",
  rounded: "rounded",
  firstSpansLarge: false,
  limit: null,
});

const emits = defineEmits<LightboxEmits>();

const state = useProvideLightbox(props, emits);
const isOpen = state.open;
const currentIndex = state.index;

function open() {
  isOpen.value = true;
}

defineExpose({
  open: isOpen,
  index: currentIndex,
  next: state.next,
  prev: state.prev,
  goTo: state.goTo,
  openAt: state.openAt,
  close: state.close,
  download: state.download,
  toggleZoom: state.toggleZoom,
  toggleFullscreen: state.toggleFullscreen,
  share: state.share,
});
</script>

<template>
  <DialogRoot v-model:open="isOpen">
    <slot
      :open="open"
      :open-at="state.openAt"
      :close="state.close"
      :go-to="state.goTo"
      :next="state.next"
      :prev="state.prev"
      :is-open="isOpen"
      :index="currentIndex"
      :items="state.items.value"
    />
  </DialogRoot>
</template>
