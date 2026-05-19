<script setup lang="ts">
import { cn } from "@/lib/utils";
import {
  DialogContent,
  DialogDescription,
  DialogOverlay,
  DialogPortal,
  DialogTitle,
  VisuallyHidden,
} from "reka-ui";
import { computed, nextTick, type HTMLAttributes } from "vue";
import { pickAlt, useLightbox } from "./useLightbox";

const props = defineProps<{
  class?: HTMLAttributes["class"];
  overlayClass?: HTMLAttributes["class"];
}>();

const state = useLightbox();

const dialogTitle = computed(() => {
  const item = state.current.value;
  if (!item) {
    return "Lightbox";
  }
  return pickAlt(item, state.props.alt) || "Lightbox";
});

function onKeyDown(event: KeyboardEvent) {
  if (!state.props.keyboard) {
    return;
  }
  if (event.key === "ArrowLeft") {
    event.preventDefault();
    state.prev();
  } else if (event.key === "ArrowRight") {
    event.preventDefault();
    state.next();
  } else if (event.key === "Home") {
    event.preventDefault();
    state.goTo(0);
  } else if (event.key === "End") {
    event.preventDefault();
    state.goTo(state.items.value.length - 1);
  } else if (event.key === " " && state.props.zoomable) {
    event.preventDefault();
    state.toggleZoom();
  }
}

function onInteractOutside(event: Event) {
  if (!state.props.closeOnBackdropClick) {
    event.preventDefault();
  }
}

function onOpenAutoFocus(event: Event) {
  event.preventDefault();
  nextTick(() => {
    const root = document.querySelector(
      "[data-slot=lightbox-content]",
    ) as HTMLElement | null;
    const focusable = root?.querySelector<HTMLElement>(
      "[data-slot=lightbox-close]",
    );
    focusable?.focus({ preventScroll: true });
  });
}
</script>

<template>
  <DialogPortal>
    <DialogOverlay
      data-slot="lightbox-overlay"
      :class="
        cn(
          'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-black/95 backdrop-blur-sm',
          props.overlayClass,
        )
      "
    />
    <DialogContent
      data-slot="lightbox-content"
      :class="
        cn(
          'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 flex flex-col overflow-hidden text-white outline-hidden duration-200',
          props.class,
        )
      "
      @keydown="onKeyDown"
      @interact-outside="onInteractOutside"
      @open-auto-focus="onOpenAutoFocus"
    >
      <VisuallyHidden as-child>
        <DialogTitle>{{ dialogTitle }}</DialogTitle>
      </VisuallyHidden>
      <VisuallyHidden as-child>
        <DialogDescription>
          Use arrow keys to navigate, Escape to close.
        </DialogDescription>
      </VisuallyHidden>
      <slot />
    </DialogContent>
  </DialogPortal>
</template>
