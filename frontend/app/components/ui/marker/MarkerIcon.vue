<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps<{ class?: HTMLAttributes["class"] }>();
</script>

<template>
  <span
    data-slot="marker-icon"
    aria-hidden="true"
    :class="cn('shrink-0 [&_svg]:size-4', props.class)"
  >
    <slot />
  </span>
</template>
