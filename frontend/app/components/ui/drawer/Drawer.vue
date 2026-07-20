<script lang="ts" setup>
import type { DrawerRootEmits, DrawerRootProps } from "vaul-vue";
import { DrawerRoot } from "vaul-vue";
import { useForwardPropsEmits } from "reka-ui";
import { useVModel } from "@vueuse/core";
import { onBeforeUnmount, ref, watch } from "vue";

const props = withDefaults(defineProps<DrawerRootProps>(), {
  shouldScaleBackground: true,
});

const emits = defineEmits<DrawerRootEmits>();

const forwarded = useForwardPropsEmits(props, emits);

const isOpen = useVModel(props, "open", emits, {
  passive: true,
  defaultValue: props.defaultOpen,
});

// Back button/gesture closes drawer instead of navigating away
const pushedHistoryState = ref(false);

const onPopState = () => {
  pushedHistoryState.value = false;
  isOpen.value = false;
};

watch(isOpen, (newVal, oldVal) => {
  if (newVal && !oldVal) {
    window.history.pushState({ drawerOpen: true }, "");
    pushedHistoryState.value = true;
    window.addEventListener("popstate", onPopState, { once: true });
  } else if (!newVal && oldVal && pushedHistoryState.value) {
    pushedHistoryState.value = false;
    window.removeEventListener("popstate", onPopState);
    window.history.back();
  }
});

onBeforeUnmount(() => {
  window.removeEventListener("popstate", onPopState);
});
</script>

<template>
  <DrawerRoot data-slot="drawer" v-bind="forwarded" v-model:open="isOpen">
    <slot />
  </DrawerRoot>
</template>
