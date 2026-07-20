<script lang="ts" setup>
import { DrawerOverlay } from "vaul-vue";
import type { DialogOverlayProps } from "reka-ui";
import { type HtmlHTMLAttributes, computed } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps<
  DialogOverlayProps & { class?: HtmlHTMLAttributes["class"] }
>();

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;

  return delegated;
});
</script>

<template>
  <DrawerOverlay
    data-slot="drawer-overlay"
    v-bind="delegatedProps"
    :class="cn('cn-drawer-overlay fixed inset-0 z-50', props.class)"
  />
</template>
