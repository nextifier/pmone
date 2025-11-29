<template>
  <DialogRoot v-model:open="isOpen">
    <DialogPortal>
      <DialogOverlay
        class="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-black/80 backdrop-blur-md"
      />
      <DialogContent
        class="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 fixed top-1/2 left-1/2 z-50 flex aspect-16/9 h-auto max-h-[calc(100%-4rem)] w-[calc(100%-8px)] max-w-6xl -translate-x-1/2 -translate-y-1/2 flex-col rounded-2xl duration-200"
        @close-auto-focus.prevent
      >
        <DialogTitle class="hidden"></DialogTitle>
        <DialogDescription class="hidden"></DialogDescription>

        <div
          class="size-full overflow-hidden rounded-xl border border-white/20 backdrop-blur-xl lg:rounded-2xl"
        >
          <iframe
            v-if="videoSrc"
            :src="`${videoSrc}?autoplay=1`"
            class="size-full border-0 bg-white/10"
            title="YouTube video player"
            frameborder="0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            referrerpolicy="strict-origin-when-cross-origin"
            allowfullscreen
          ></iframe>
        </div>

        <DialogClose
          class="absolute bottom-0 left-1/2 flex size-10 -translate-x-1/2 translate-y-[calc(100%+1rem)] items-center justify-center rounded-full bg-white text-black transition hover:bg-white/80 active:scale-98 lg:top-0 lg:right-0 lg:bottom-auto lg:left-auto lg:translate-x-[calc(100%+1rem)] lg:translate-y-0"
          aria-label="Close"
        >
          <IconClose class="size-5" />
        </DialogClose>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>

<script setup>
import {
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
} from "reka-ui";

const uiStore = useUiStore();

const isOpen = computed({
  get() {
    return uiStore.isEmbedVideoDialogOpen;
  },
  set(value) {
    if (!value) {
      uiStore.closeEmbedVideoDialog();
    }
  },
});

const videoSrc = computed(() => uiStore.embedVideoSrc);
</script>
