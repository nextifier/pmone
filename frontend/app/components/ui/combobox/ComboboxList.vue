<script setup lang="ts">
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { ComboboxContentEmits, ComboboxContentProps } from "reka-ui";
import { ComboboxContent, ComboboxPortal, useForwardPropsEmits } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = withDefaults(
  defineProps<ComboboxContentProps & { class?: HTMLAttributes["class"] }>(),
  {
    position: "popper",
    align: "center",
    sideOffset: 4,
  }
);
const emits = defineEmits<ComboboxContentEmits>();

const delegatedProps = reactiveOmit(props, "class");
const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <ComboboxPortal>
    <ComboboxContent
      data-slot="combobox-list"
      v-bind="forwarded"
      :class="
        cn(
          'cn-combobox-content cn-combobox-content-logical cn-menu-target group/combobox-content z-50 w-(--reka-combobox-trigger-width) min-w-[8rem]',
          props.class
        )
      "
    >
      <slot />
    </ComboboxContent>
  </ComboboxPortal>
</template>
