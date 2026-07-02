<script setup lang="ts">
import { cn } from "@/lib/utils";
import type { HTMLAttributes } from "vue";

const props = withDefaults(
  defineProps<{
    class?: HTMLAttributes["class"];
    size?: "default" | "sm";
  }>(),
  { size: "default" },
);
</script>

<template>
  <div
    data-slot="card"
    :data-size="size"
    :class="cn('cn-card group/card flex flex-col', props.class)"
  >
    <slot />
  </div>
</template>
