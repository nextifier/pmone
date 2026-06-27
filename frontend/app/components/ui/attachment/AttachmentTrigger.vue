<script setup lang="ts">
import type { PrimitiveProps } from "reka-ui";
import type { HTMLAttributes } from "vue";
import { Primitive } from "reka-ui";
import { cn } from "@/lib/utils";

const props = withDefaults(
  defineProps<PrimitiveProps & { class?: HTMLAttributes["class"] }>(),
  { as: "button" }
);
</script>

<template>
  <Primitive
    :as="as"
    :as-child="asChild"
    :type="asChild ? undefined : 'button'"
    data-slot="attachment-trigger"
    :class="cn('absolute inset-0 z-10 outline-none', props.class)"
  >
    <slot />
  </Primitive>
</template>
