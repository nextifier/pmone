<template>
  <div>
    <slot name="trigger" :open="open" />

    <DialogRoot
      v-if="isDesktop && isOpen && isResponsive"
      v-model:open="isOpen"
    >
      <DialogPortal>
        <DialogOverlay
          class="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-black/80"
        />
        <DialogContent
          class="bg-background data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 fixed top-1/2 left-1/2 z-50 flex max-h-[calc(100%-4rem)] w-full -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden rounded-2xl border shadow-lg duration-200"
          :style="{ maxWidth: dialogMaxWidth }"
        >
          <DialogTitle class="hidden" />
          <DialogDescription class="hidden" />
          <slot name="sticky-header" />
          <ScrollArea class="flex flex-col" :scrollHideDelay="0">
            <slot :data="dialogData" />
          </ScrollArea>
          <slot name="sticky-footer" />
          <DialogClose
            class="data-[state=open]:bg-muted data-[state=open]:text-muted-foreground hover:bg-muted absolute top-3 right-3 flex size-8 items-center justify-center rounded-full focus:outline-hidden disabled:pointer-events-none"
          >
            <IconClose class="size-4" />
            <span class="sr-only">Close</span>
          </DialogClose>
        </DialogContent>
      </DialogPortal>
    </DialogRoot>

    <DrawerRoot v-else v-model:open="isOpen">
      <DrawerPortal>
        <DrawerOverlay class="fixed inset-0 z-50 bg-black/80" />
        <DrawerContent
          class="border-border bg-background fixed inset-x-0 bottom-0 z-50 mt-24 flex h-auto max-h-[80vh] flex-col rounded-t-2xl border-t outline-hidden lg:max-h-[calc(100lvh-var(--navbar-height-desktop))]"
        >
          <div
            class="bg-border mx-auto mt-2 mb-7 h-1.5 w-[100px] shrink-0 rounded-full"
          />
          <DrawerTitle class="hidden" />
          <DrawerDescription class="hidden" />
          <slot name="sticky-header" />
          <div
            ref="drawerContentBody"
            class="pointer-events-auto"
            :class="{
              'touch-pan-down': drawerContentBodyIsAtTop,
              'overflow-y-auto': props.overflowContent,
            }"
            @scroll="handleDrawerContentBodyScroll"
          >
            <slot :data="dialogData" />
          </div>
          <slot name="sticky-footer" />
          <DrawerClose
            v-if="drawerCloseButton || isDesktop"
            class="group data-[state=open]:bg-muted data-[state=open]:text-muted-foreground hover:bg-muted absolute top-1.5 right-3 flex size-8 items-center justify-center rounded-full focus:outline-hidden disabled:pointer-events-none"
          >
            <IconClose
              class="size-4 opacity-60 transition group-hover:opacity-100"
            />
            <span class="sr-only">Close</span>
          </DrawerClose>
        </DrawerContent>
      </DrawerPortal>
    </DrawerRoot>
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
  DrawerRoot,
  DrawerContent,
  DrawerPortal,
  DrawerOverlay,
  DrawerTitle,
  DrawerDescription,
  DrawerClose,
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
    default: false,
  },
  dialogMaxWidth: {
    type: String,
    default: "400px",
  },
  drawerCloseButton: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:open"]);

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

// Focus management for drawer to fix aria-hidden warning
watch(isOpen, (newValue) => {
  if (newValue && !isDesktop.value) {
    // When drawer opens on mobile, blur the active element
    nextTick(() => {
      if (document.activeElement instanceof HTMLElement) {
        document.activeElement.blur();
      }
    });
  }
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
