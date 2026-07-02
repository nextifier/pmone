<script setup lang="ts">
import { cn } from "@/lib/utils";
import type { PrimitiveProps } from "reka-ui";
import { Primitive } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = defineProps<
  PrimitiveProps & {
    class?: HTMLAttributes["class"];
  }
>();
</script>

<template>
  <Primitive
    data-slot="sidebar-group-action"
    data-sidebar="group-action"
    :as="as"
    :as-child="asChild"
    :class="
      cn(
        'cn-sidebar-group-action flex aspect-square items-center justify-center outline-hidden transition-transform group-data-[collapsible=icon]:hidden after:absolute after:-inset-2 lg:after:hidden [&>svg]:shrink-0',
        props.class
      )
    "
  >
    <slot />
  </Primitive>
</template>
