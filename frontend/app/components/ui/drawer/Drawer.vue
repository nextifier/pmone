<script lang="ts" setup>
import type { DrawerRootEmits, DrawerRootProps } from "reka-ui";
import { DrawerRoot, useForwardPropsEmits } from "reka-ui";
import { reactiveOmit, useVModel } from "@vueuse/core";
import { computed } from "vue";
import { useDrawerHistory } from "./useDrawerHistory";

export type DrawerSide = "top" | "right" | "bottom" | "left";

const SIDE_TO_SWIPE_DIRECTION = {
  top: "up",
  bottom: "down",
  left: "left",
  right: "right",
} as const;

const props = withDefaults(
  defineProps<Omit<DrawerRootProps, "swipeDirection"> & { side?: DrawerSide }>(),
  { side: "bottom" }
);

const emits = defineEmits<DrawerRootEmits>();

const delegatedProps = reactiveOmit(props, "side", "open");
const forwarded = useForwardPropsEmits(delegatedProps, emits);

const swipeDirection = computed(() => SIDE_TO_SWIPE_DIRECTION[props.side]);

const isOpen = useVModel(props, "open", emits, {
  passive: true,
  defaultValue: props.defaultOpen,
});

useDrawerHistory(isOpen);
</script>

<template>
  <!-- No `data-slot` here: DrawerRoot renders a fragment, so Vue cannot inherit it. -->
  <DrawerRoot
    v-bind="forwarded"
    v-model:open="isOpen"
    :swipe-direction="swipeDirection"
  >
    <slot />
  </DrawerRoot>
</template>
