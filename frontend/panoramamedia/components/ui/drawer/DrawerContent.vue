<script lang="ts" setup>
import { DrawerContent, DrawerPortal } from "vaul-vue";
import type { DialogContentEmits, DialogContentProps } from "reka-ui";
import { useForwardPropsEmits } from "reka-ui";
import type { HtmlHTMLAttributes } from "vue";
import DrawerOverlay from "./DrawerOverlay.vue";
import { cn } from "@/lib/utils";

const props = defineProps({
  class: { type: String, required: false },
  overflowContent: { type: Boolean, default: true },
});

const emits = defineEmits<DialogContentEmits>();

const forwarded = useForwardPropsEmits(props, emits);

const drawerContentBody = ref(null);
const yPosition = ref(0);
const isAtTop = ref(true);

const handleScroll = () => {
  if (drawerContentBody.value) {
    yPosition.value = drawerContentBody.value.scrollTop;
    isAtTop.value = yPosition.value === 0;
  }
};
</script>

<template>
  <DrawerPortal>
    <DrawerOverlay />
    <DrawerContent
      v-bind="forwarded"
      :class="
        cn(
          'fixed inset-x-0 bottom-0 z-50 mt-24 flex h-auto max-h-[80vh] flex-col rounded-t-2xl border-t bg-white outline-hidden lg:max-h-[calc(100lvh-var(--navbar-height-desktop))] dark:bg-gray-950',
          props.class,
        )
      "
    >
      <div
        class="mx-auto mt-2 mb-4 h-1.5 w-[100px] shrink-0 rounded-full bg-gray-200 dark:bg-gray-800"
      />

      <div
        ref="drawerContentBody"
        class="pointer-events-auto"
        :class="{
          'touch-pan-down': isAtTop,
          'overflow-y-auto': props.overflowContent,
        }"
        @scroll="handleScroll"
      >
        <slot />
      </div>
    </DrawerContent>
  </DrawerPortal>
</template>
