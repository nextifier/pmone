<script lang="ts" setup>
import type { HTMLAttributes } from "vue";
import { useVModel } from "@vueuse/core";
import { RadioGroupRoot } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps<{
  class?: HTMLAttributes["class"];
  modelValue?: string;
  defaultValue?: string;
}>();

const emits = defineEmits<{ "update:modelValue": [value: string] }>();

// Without this a group used with no `v-model` would be controlled at
// `undefined`, so no item could ever become the selected one.
const selected = useVModel(props, "modelValue", emits, {
  passive: true,
  defaultValue: props.defaultValue,
});
</script>

<template>
  <!-- Classes copied verbatim from coss drawer.tsx (DrawerMenuRadioGroup). -->
  <RadioGroupRoot
    v-model="selected"
    data-slot="drawer-menu-radio-group"
    :class="cn('flex flex-col', props.class)"
  >
    <slot />
  </RadioGroupRoot>
</template>
