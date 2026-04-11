<template>
  <div>
    <slot name="trigger" :open="open" />

    <template v-if="isDesktop && isOpen && isResponsive">
      <DialogRoot v-model:open="isOpen">
        <DialogPortal>
          <DialogOverlay
            v-if="!hideOverlay"
            class="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-black/80"
          />
          <DialogContent
            class="bg-background data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 fixed top-1/2 left-1/2 z-50 flex w-full -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden rounded-2xl border shadow-lg outline-hidden duration-200"
            :style="{ maxWidth: dialogMaxWidth, maxHeight: dialogMaxHeight }"
            @interact-outside="onInteractOutside"
            @escape-key-down="onEscapeKeyDown"
          >
            <DialogTitle class="hidden" />
            <DialogDescription class="hidden" />
            <slot name="sticky-header" />
            <ScrollArea class="flex flex-col" :scrollHideDelay="0">
              <slot :data="dialogData" />
            </ScrollArea>
            <slot name="sticky-footer" />
            <button
              v-if="preventClose"
              @click="onCloseButtonClick"
              class="data-[state=open]:bg-muted data-[state=open]:text-muted-foreground hover:bg-muted absolute top-3 right-3 z-20 flex size-8 items-center justify-center rounded-full focus:outline-hidden disabled:pointer-events-none"
            >
              <Icon name="lucide:x" class="size-4" />
              <span class="sr-only">Close</span>
            </button>
            <DialogClose
              v-else
              class="data-[state=open]:bg-muted data-[state=open]:text-muted-foreground hover:bg-muted absolute top-3 right-3 z-20 flex size-8 items-center justify-center rounded-full focus:outline-hidden disabled:pointer-events-none"
            >
              <Icon name="lucide:x" class="size-4" />
              <span class="sr-only">Close</span>
            </DialogClose>
          </DialogContent>
        </DialogPortal>
      </DialogRoot>
    </template>

    <template v-else>
      <DrawerRoot v-model:open="isOpen">
        <DrawerPortal>
          <DrawerOverlay v-if="!hideOverlay" class="fixed inset-0 z-50 bg-black/80" />
          <DrawerContent
            class="border-border bg-background fixed inset-x-0 bottom-0 z-50 mt-24 flex h-auto max-h-[85vh] flex-col rounded-t-2xl border-t outline-hidden lg:max-h-[calc(100lvh-var(--navbar-height-desktop))]"
          >
            <div class="bg-border mx-auto mt-2 mb-7 h-1.5 w-[100px] shrink-0 rounded-full" />
            <DrawerTitle class="hidden" />
            <DrawerDescription class="hidden" />
            <slot name="sticky-header" />
            <div
              ref="drawerContentBody"
              class="pointer-events-auto overflow-x-hidden wrap-break-word"
              :class="{
                'touch-pan-down': drawerContentBodyIsAtTop,
                'overflow-y-auto': props.overflowContent,
              }"
              @scroll="handleDrawerContentBodyScroll"
            >
              <slot :data="dialogData" />
            </div>
            <slot name="sticky-footer" />
            <button
              v-if="preventClose"
              @click="onCloseButtonClick"
              class="group data-[state=open]:bg-muted data-[state=open]:text-muted-foreground hover:bg-muted absolute top-1.5 right-3 z-20 flex size-8 items-center justify-center rounded-full focus:outline-hidden disabled:pointer-events-none"
            >
              <Icon
                name="hugeicons:cancel-01"
                class="size-4 opacity-60 transition group-hover:opacity-100"
              />
              <span class="sr-only">Close</span>
            </button>
            <DrawerClose
              v-else-if="drawerCloseButton || isDesktop"
              class="group data-[state=open]:bg-muted data-[state=open]:text-muted-foreground hover:bg-muted absolute top-1.5 right-3 z-20 flex size-8 items-center justify-center rounded-full focus:outline-hidden disabled:pointer-events-none"
            >
              <Icon
                name="hugeicons:cancel-01"
                class="size-4 opacity-60 transition group-hover:opacity-100"
              />
              <span class="sr-only">Close</span>
            </DrawerClose>
          </DrawerContent>
        </DrawerPortal>
      </DrawerRoot>
    </template>
  </div>
</template>

<script setup>
import { useMediaQuery, useVModel } from "@vueuse/core";

import {
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
} from "reka-ui";
import {
  DrawerClose,
  DrawerContent,
  DrawerDescription,
  DrawerOverlay,
  DrawerPortal,
  DrawerRoot,
  DrawerTitle,
} from "vaul-vue";

const props = defineProps({
  open: {
    type: Boolean,
    default: undefined,
  },
  isResponsive: {
    type: Boolean,
    default: true,
  },
  overflowContent: {
    type: Boolean,
    default: true,
  },
  dialogMaxWidth: {
    type: String,
    default: "400px",
  },
  dialogMaxHeight: {
    type: String,
    default: "calc(100% - 4rem)",
  },
  drawerCloseButton: {
    type: Boolean,
    default: false,
  },
  preventClose: {
    type: Boolean,
    default: false,
  },
  hideOverlay: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:open", "close-prevented"]);

const isDesktop = useMediaQuery("(min-width: 768px)");

// const isOpen = ref(false);
const isOpen = useVModel(props, "open", emit, { passive: true });
const dialogData = ref({});

const open = (data = {}) => {
  dialogData.value = data;
  isOpen.value = true;
};

const close = () => {
  isOpen.value = false;
};

provide("dialogControls", {
  open,
  close,
});

// Prevent close handlers
const onInteractOutside = (event) => {
  if (props.preventClose) {
    event.preventDefault();
    emit("close-prevented");
  }
};

const onEscapeKeyDown = (event) => {
  if (props.preventClose) {
    event.preventDefault();
    emit("close-prevented");
  }
};

const onCloseButtonClick = () => {
  emit("close-prevented");
};

// Drawer: detect close while form is dirty and emit close-prevented
// We let the drawer close (no re-open) to avoid double-drawer stacking.
// FormTask stays in DOM inside the closed DrawerPortal, so save still works.
watch(isOpen, (newValue, oldValue) => {
  // On desktop dialog: pages without scrollbar don't get padding-right from reka-ui's scroll lock,
  // so scrollbar-gutter stays "stable" and the custom scrollbar remains visible.
  // Fix: after reka-ui sets overflow:hidden on body, add padding-right to trigger the CSS rule
  // that switches scrollbar-gutter to "auto" (same mechanism that works on pages WITH scrollbar).
  if (isDesktop.value && props.isResponsive) {
    if (newValue) {
      const hasScrollbar =
        document.documentElement.scrollHeight > document.documentElement.clientHeight;
      if (!hasScrollbar) {
        const test = document.createElement("div");
        test.style.cssText =
          "overflow:scroll;width:100px;height:100px;position:absolute;top:-999px";
        document.body.appendChild(test);
        const gutterWidth = test.offsetWidth - test.clientWidth;
        document.body.removeChild(test);

        if (gutterWidth > 0) {
          // Wait for reka-ui to set body overflow:hidden, then append padding-right
          const observer = new MutationObserver(() => {
            if (document.body.style.overflow === "hidden") {
              document.body.style.paddingRight = gutterWidth + "px";
              observer.disconnect();
            }
          });
          observer.observe(document.body, {
            attributes: true,
            attributeFilter: ["style"],
          });
        }
      }
    }
  }

  if (!isDesktop.value) {
    if (newValue) {
      // Blur active element before drawer renders to prevent aria-hidden warning
      if (document.activeElement instanceof HTMLElement) {
        document.activeElement.blur();
      }
    }

    if (oldValue && !newValue && props.preventClose) {
      emit("close-prevented");
    }
  }
});

// Back button/gesture closes drawer instead of navigating away
const pushedHistoryState = ref(false);

const onPopState = () => {
  pushedHistoryState.value = false;
  isOpen.value = false;
};

watch(isOpen, (newVal, oldVal) => {
  if (!isDesktop.value) {
    if (newVal && !oldVal) {
      window.history.pushState({ drawerOpen: true }, "");
      pushedHistoryState.value = true;
      window.addEventListener("popstate", onPopState, { once: true });
    } else if (!newVal && oldVal && pushedHistoryState.value) {
      pushedHistoryState.value = false;
      window.removeEventListener("popstate", onPopState);
      window.history.back();
    }
  }
});

onBeforeUnmount(() => {
  window.removeEventListener("popstate", onPopState);
});

const drawerContentBody = ref(null);
const drawerContentBodyYPosition = ref(0);
const drawerContentBodyIsAtTop = ref(true);

const handleDrawerContentBodyScroll = () => {
  if (drawerContentBody.value) {
    drawerContentBodyYPosition.value = drawerContentBody.value.scrollTop;
    drawerContentBodyIsAtTop.value = drawerContentBodyYPosition.value === 0;
  }
};
</script>
