<script setup lang="ts">
import { cn } from "@/lib/utils";
import type { PrimitiveProps } from "reka-ui";
import { Primitive } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = withDefaults(
  defineProps<
    PrimitiveProps & {
      showOnHover?: boolean;
      class?: HTMLAttributes["class"];
    }
  >(),
  {
    as: "button",
  }
);
</script>

<template>
  <Primitive
    data-slot="sidebar-menu-action"
    data-sidebar="menu-action"
    :class="
      cn(
        'cn-sidebar-menu-action flex items-center justify-center outline-hidden transition-transform group-data-[collapsible=icon]:hidden after:absolute after:-inset-2 lg:after:hidden [&>svg]:shrink-0',
        showOnHover &&
          'peer-data-[active=true]/menu-button:text-sidebar-accent-foreground group-focus-within/menu-item:opacity-100 group-hover/menu-item:opacity-100 data-[state=open]:opacity-100 lg:opacity-0',
        props.class
      )
    "
    :as="as"
    :as-child="asChild"
  >
    <slot />
  </Primitive>
</template>
