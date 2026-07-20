<script setup lang="ts">
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import { Check } from "lucide-vue-next";
import type { SelectItemProps } from "reka-ui";
import { SelectItem, SelectItemIndicator, SelectItemText, useForwardProps } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = defineProps<SelectItemProps & { class?: HTMLAttributes["class"] }>();

const delegatedProps = reactiveOmit(props, "class");

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
  <SelectItem
    data-slot="select-item"
    v-bind="forwardedProps"
    :class="
      cn(
        'cn-select-item relative flex w-full cursor-pointer items-center outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0',
        props.class
      )
    "
  >
    <span data-slot="select-item-indicator" class="cn-select-item-indicator">
      <SelectItemIndicator>
        <Check class="cn-select-item-indicator-icon pointer-events-none" />
      </SelectItemIndicator>
    </span>

    <SelectItemText>
      <slot />
    </SelectItemText>
  </SelectItem>
</template>
