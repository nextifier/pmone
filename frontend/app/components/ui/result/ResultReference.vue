<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

/**
 * The order / booking reference under the description. Layout only, on purpose:
 * ButtonCopy pulls in a tippy directive and useClipboard, and a Result that shows
 * no reference should not carry either. Put <ButtonCopy> in the slot at the call
 * site instead.
 */
const props = defineProps<{
  class?: HTMLAttributes["class"];
  value?: string | number;
}>();
</script>

<template>
  <div
    data-slot="result-reference"
    data-result-reveal
    style="--result-i: 3"
    :class="cn('flex items-center justify-center gap-x-2', props.class)"
  >
    <code
      v-if="value !== undefined && value !== null && value !== ''"
      data-slot="result-reference-value"
      class="bg-muted/60 text-foreground inline-flex items-center rounded px-2 py-1 font-mono text-sm tracking-tight sm:text-base"
    >
      {{ value }}
    </code>
    <slot />
  </div>
</template>
