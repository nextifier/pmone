<script setup lang="ts">
import type { PrimitiveProps } from "reka-ui";
import type { HTMLAttributes } from "vue";
import type { MarkerVariants } from ".";
import { Primitive } from "reka-ui";
import { cn } from "@/lib/utils";
import { markerVariants } from ".";

interface Props extends PrimitiveProps {
  variant?: MarkerVariants["variant"];
  class?: HTMLAttributes["class"];
}

const props = withDefaults(defineProps<Props>(), {
  as: "div",
  variant: "default",
});
</script>

<template>
  <Primitive
    :as="as"
    :as-child="asChild"
    data-slot="marker"
    :data-variant="variant"
    :class="cn(markerVariants({ variant }), props.class)"
  >
    <slot />
  </Primitive>
</template>
