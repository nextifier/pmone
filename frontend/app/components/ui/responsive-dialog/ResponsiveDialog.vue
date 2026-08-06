<template>
  <div>
    <slot name="trigger" :open="open" />

    <template v-if="isDesktop && isOpen && isResponsive">
      <DialogRoot v-model:open="isOpen">
        <DialogPortal>
          <DialogOverlay
            v-if="!hideOverlay"
            class="cn-dialog-overlay data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 ease-(--modal-ease) data-open:duration-(--modal-open-dur) data-closed:duration-(--modal-close-dur) motion-reduce:animate-none! fixed inset-0 z-50"
          />
          <DialogContent
            class="bg-background data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-96 data-[state=open]:zoom-in-96 ease-(--modal-ease) data-open:duration-(--modal-open-dur) data-closed:duration-(--modal-close-dur) motion-reduce:animate-none! border-border fixed top-1/2 left-1/2 z-50 flex w-full -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden rounded-2xl shadow-lg outline-hidden dark:border"
            :style="{ maxWidth: dialogMaxWidth, maxHeight: dialogMaxHeight }"
            @interact-outside="onInteractOutside"
            @escape-key-down="onEscapeKeyDown"
          >
            <DialogTitle class="sr-only">{{ title }}</DialogTitle>
            <DialogDescription class="sr-only">{{ description }}</DialogDescription>
            <slot name="sticky-header" />
            <ScrollArea
              v-if="!flushContent"
              class="flex flex-col"
              :scrollHideDelay="0"
            >
              <slot :data="dialogData" />
            </ScrollArea>
            <div
              v-else
              class="flex flex-col overflow-y-auto [&::-webkit-scrollbar]:hidden"
              style="scrollbar-width: none"
            >
              <slot :data="dialogData" />
            </div>
            <slot name="sticky-footer" />
            <button
              v-if="preventClose"
              @click="onCloseButtonClick"
              :class="
                cn(
                  'data-[state=open]:bg-muted data-[state=open]:text-muted-foreground hover:bg-muted absolute top-3 right-3 z-20 flex size-8 items-center justify-center rounded-full focus:outline-hidden',
                  props.closeButtonClass,
                )
              "
            >
              <Icon name="lucide:x" class="size-4" />
              <span class="sr-only">Close</span>
            </button>
            <DialogClose
              v-else
              :class="
                cn(
                  'data-[state=open]:bg-muted data-[state=open]:text-muted-foreground hover:bg-muted absolute top-3 right-3 z-20 flex size-8 items-center justify-center rounded-full focus:outline-hidden',
                  props.closeButtonClass,
                )
              "
            >
              <Icon name="lucide:x" class="size-4" />
              <span class="sr-only">Close</span>
            </DialogClose>
          </DialogContent>
        </DialogPortal>
      </DialogRoot>
    </template>

    <template v-else>
      <Drawer v-model:open="isOpen" side="bottom">
        <DrawerPopup
          :show-bar="!flushContent"
          :class="
            cn(
              'max-h-[85vh] lg:max-h-[calc(100lvh-var(--navbar-height-desktop))]',
              flushContent && 'overflow-hidden',
            )
          "
          :overlay-class="hideOverlay && 'pointer-events-none opacity-0'"
        >
          <DrawerTitle class="sr-only">{{ title }}</DrawerTitle>
          <DrawerDescription class="sr-only">{{ description }}</DrawerDescription>
          <slot name="sticky-header" />
          <!--
            No `touch-action` juggling here any more: `useDrawerSwipeArbiter`
            decides per gesture whether the drag belongs to the panel or to this
            scroller, so the content scrolls while the panel stays draggable.
          -->
          <DrawerPanel
            class="overflow-x-hidden p-0 wrap-break-word"
            :scrollable="props.overflowContent"
            :scroll-fade="false"
          >
            <slot :data="dialogData" />
          </DrawerPanel>
          <slot name="sticky-footer" />
          <button
            v-if="preventClose"
            @click="onCloseButtonClick"
            class="group data-[state=open]:bg-muted data-[state=open]:text-muted-foreground hover:bg-muted absolute top-1.5 right-3 z-20 flex size-8 items-center justify-center rounded-full focus:outline-hidden"
          >
            <Icon
              name="hugeicons:cancel-01"
              class="size-4 opacity-60 transition-opacity group-hover:opacity-100"
            />
            <span class="sr-only">Close</span>
          </button>
          <DrawerClose
            v-else-if="drawerCloseButton || isDesktop"
            class="group data-[state=open]:bg-muted data-[state=open]:text-muted-foreground hover:bg-muted absolute top-1.5 right-3 z-20 flex size-8 items-center justify-center rounded-full focus:outline-hidden"
          >
            <Icon
              name="hugeicons:cancel-01"
              class="size-4 opacity-60 transition-opacity group-hover:opacity-100"
            />
            <span class="sr-only">Close</span>
          </DrawerClose>
        </DrawerPopup>
      </Drawer>
    </template>
  </div>
</template>

<script setup>
import { useMediaQuery, useVModel } from "@vueuse/core";
import { cn } from "@/lib/utils";

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
  Drawer,
  DrawerClose,
  DrawerDescription,
  DrawerPanel,
  DrawerPopup,
  DrawerTitle,
} from "@/components/ui/drawer";

const props = defineProps({
  /** Accessible name. Rendered visually hidden, so pass it even when the design has no heading. */
  title: {
    type: String,
    default: "",
  },
  /** Accessible description, also visually hidden. */
  description: {
    type: String,
    default: "",
  },
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
  closeButtonClass: {
    type: String,
    default: "",
  },
  flushContent: {
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
        document.documentElement.scrollHeight >
        document.documentElement.clientHeight;
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

// The history guard (back button closes instead of navigating) lives in
// <Drawer>, so this component no longer keeps its own copy.


</script>
