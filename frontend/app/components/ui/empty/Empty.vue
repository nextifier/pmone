<script setup lang="ts">
import { cn } from "@/lib/utils";
import type { HTMLAttributes } from "vue";

const props = defineProps<{
  class?: HTMLAttributes["class"];
}>();
</script>

<template>
  <div
    data-slot="empty"
    :class="
      cn(
        // Texts reveal (transitions-dev 18). The empty state is mounted by a
        // v-if when there is nothing to show, so mounting is the trigger — the
        // stack rises once, media first, and never replays while it is on screen.
        'cn-empty t-stagger flex w-full min-w-0 flex-1 flex-col items-center justify-center text-center text-balance',
        props.class
      )
    "
  >
    <slot />
  </div>
</template>
