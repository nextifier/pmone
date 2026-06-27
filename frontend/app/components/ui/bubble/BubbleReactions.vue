<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import type { BubbleReactionsVariants } from ".";
import { cn } from "@/lib/utils";
import { bubbleReactionsVariants } from ".";

const props = withDefaults(
  defineProps<{
    class?: HTMLAttributes["class"];
    side?: BubbleReactionsVariants["side"];
    align?: BubbleReactionsVariants["align"];
  }>(),
  { side: "bottom", align: "end" }
);
</script>

<template>
  <div
    data-slot="bubble-reactions"
    :data-align="align"
    :data-side="side"
    :class="cn(bubbleReactionsVariants({ side, align }), props.class)"
  >
    <slot />
  </div>
</template>
