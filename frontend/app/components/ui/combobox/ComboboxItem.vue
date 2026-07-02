<script setup lang="ts">
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { ComboboxItemEmits, ComboboxItemProps } from "reka-ui";
import { ComboboxItem, useForwardPropsEmits } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = defineProps<ComboboxItemProps & { class?: HTMLAttributes["class"] }>();
const emits = defineEmits<ComboboxItemEmits>();

const delegatedProps = reactiveOmit(props, "class");

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <ComboboxItem
    data-slot="combobox-item"
    v-bind="forwarded"
    :class="
      cn(
        'cn-combobox-item relative flex w-full cursor-pointer items-center outline-hidden select-none data-disabled:pointer-events-none data-disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0',
        props.class
      )
    "
  >
    <slot />
  </ComboboxItem>
</template>
