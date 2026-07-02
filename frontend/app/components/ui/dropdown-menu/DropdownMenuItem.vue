<script setup lang="ts">
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { DropdownMenuItemProps } from "reka-ui";
import { DropdownMenuItem, useForwardProps } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = withDefaults(
  defineProps<
    DropdownMenuItemProps & {
      class?: HTMLAttributes["class"];
      inset?: boolean;
      variant?: "default" | "destructive";
    }
  >(),
  {
    variant: "default",
  }
);

const delegatedProps = reactiveOmit(props, "inset", "variant", "class");

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
  <DropdownMenuItem
    data-slot="dropdown-menu-item"
    :data-inset="inset ? '' : undefined"
    :data-variant="variant"
    v-bind="forwardedProps"
    :class="
      cn(
        'cn-dropdown-menu-item group/dropdown-menu-item relative flex cursor-pointer items-center outline-hidden select-none data-disabled:pointer-events-none data-disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0',
        props.class
      )
    "
  >
    <slot />
  </DropdownMenuItem>
</template>
