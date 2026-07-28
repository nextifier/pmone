<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import type { ResultContext } from ".";
import { computed, inject } from "vue";
import { cn } from "@/lib/utils";
import { RESULT_CONTEXT } from ".";

const props = defineProps<{
  class?: HTMLAttributes["class"];
  announce?: boolean;
}>();

const context = inject<ResultContext | null>(RESULT_CONTEXT, null);

/* role="status" sits here rather than on the root. It implies aria-atomic="true",
   so on the root a screen reader would re-read the whole order summary whenever
   anything below changed. Scoped to media + title + description it announces the
   outcome once, which is the whole point. */
const announce = computed(() => props.announce ?? context?.announce ?? true);
</script>

<template>
  <div
    data-slot="result-header"
    :role="announce ? 'status' : undefined"
    :class="cn('flex max-w-md flex-col items-center gap-2', props.class)"
  >
    <slot />
  </div>
</template>
