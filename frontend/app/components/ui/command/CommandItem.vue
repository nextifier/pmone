<script setup lang="ts">
import { cn } from "@/lib/utils";
import type { ComboboxItemEmits, ComboboxItemProps } from "reka-ui";
import { ComboboxItem, useForwardPropsEmits } from "reka-ui";
import { computed, type HTMLAttributes } from "vue";

const props = defineProps<ComboboxItemProps & { class?: HTMLAttributes["class"] }>();
const emits = defineEmits<ComboboxItemEmits>();

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;

  return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <ComboboxItem
    v-bind="forwarded"
    :class="
      cn(
        'cn-command-item group/command-item cursor-pointer data-[disabled=true]:pointer-events-none data-[disabled=true]:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0',
        props.class
      )
    "
  >
    <slot />
  </ComboboxItem>
</template>
