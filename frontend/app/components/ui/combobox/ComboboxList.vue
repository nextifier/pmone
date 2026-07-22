<script setup lang="ts">
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { ComboboxContentEmits, ComboboxContentProps } from "reka-ui";
import { ComboboxContent, ComboboxPortal, useForwardPropsEmits } from "reka-ui";
import type { HTMLAttributes } from "vue";

defineOptions({
  inheritAttrs: false,
});

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
      data-slot="combobox-content"
      v-bind="{ ...$attrs, ...forwarded }"
      :class="
        cn(
          // `cn-combobox-list` caps the viewport with
          // `max-h-[min(…, calc(var(--available-height) - …))]`. That variable comes from
          // Base UI upstream; reka names it `--reka-combobox-content-available-height`, so
          // without this alias the calc() is invalid, the whole min() drops, and the
          // viewport grows past the panel and gets clipped with no scrollbar.
          'cn-combobox-content cn-combobox-content-logical cn-menu-target cn-menu-translucent group/combobox-content z-50 w-(--reka-combobox-trigger-width) [--available-height:var(--reka-combobox-content-available-height)]',
          props.class
        )
      "
    >
      <slot />
    </ComboboxContent>
  </ComboboxPortal>
</template>
