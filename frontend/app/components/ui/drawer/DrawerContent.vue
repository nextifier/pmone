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
          'cn-drawer-content group/drawer-content fixed z-50',
          props.class,
        )
      "
    >
      <div
        class="cn-drawer-handle mx-auto hidden shrink-0 group-data-[vaul-drawer-direction=bottom]/drawer-content:block"
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
