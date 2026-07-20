<script setup lang="ts">
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import { Check } from "lucide-vue-next";
import type { DropdownMenuRadioItemEmits, DropdownMenuRadioItemProps } from "reka-ui";
import { DropdownMenuItemIndicator, DropdownMenuRadioItem, useForwardPropsEmits } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = defineProps<DropdownMenuRadioItemProps & { class?: HTMLAttributes["class"] }>();

const emits = defineEmits<DropdownMenuRadioItemEmits>();

const delegatedProps = reactiveOmit(props, "class");

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <DropdownMenuRadioItem
    data-slot="dropdown-menu-radio-item"
    v-bind="forwarded"
    :class="
      cn(
        'cn-dropdown-menu-radio-item relative flex cursor-pointer items-center outline-hidden select-none data-disabled:pointer-events-none data-disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0',
        props.class
      )
    "
  >
    <span
      class="cn-dropdown-menu-item-indicator pointer-events-none"
      data-slot="dropdown-menu-radio-item-indicator"
    >
      <DropdownMenuItemIndicator>
        <Check class="size-4" />
      </DropdownMenuItemIndicator>
    </span>
    <slot />
  </DropdownMenuRadioItem>
</template>
