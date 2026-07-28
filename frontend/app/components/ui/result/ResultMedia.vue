<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import type { ResultContext, ResultMediaVariants, ResultStatus, ResultVariants } from ".";
import { computed, inject } from "vue";
import { cn } from "@/lib/utils";
import { RESULT_CONTEXT, resultMediaVariants, resultStatusIcons } from ".";

const props = defineProps<{
  class?: HTMLAttributes["class"];
  status?: ResultStatus;
  variant?: ResultMediaVariants["variant"];
  size?: ResultVariants["size"];
  /** Overrides the status glyph. On success this also gives up the drawn stroke. */
  icon?: string;
}>();

const context = inject<ResultContext | null>(RESULT_CONTEXT, null);

const status = computed(() => props.status ?? context?.status ?? "success");
const variant = computed(() => props.variant ?? context?.variant ?? "soft");
const size = computed(() => props.size ?? context?.size ?? "default");

/**
 * Only the built-in checkmark can animate its stroke: the dash length has to be
 * known up front, and an Iconify-injected path's length is not. Any custom icon
 * still gets the wrapper entrance, just not the draw.
 */
const drawsCheck = computed(() => status.value === "success" && !props.icon);

const glyph = computed(() => props.icon ?? resultStatusIcons[status.value]);
</script>

<template>
  <div
    data-slot="result-media"
    data-result-check
    :data-status="status"
    :data-variant="variant"
    :class="cn(resultMediaVariants({ status, variant, size }), props.class)"
  >
    <slot>
      <!-- Path length is 20.524 (4.5√2 + √200.5), so a 21 dasharray covers it with
           a hair to spare. Round caps hide the sub-pixel overshoot. The dasharray
           itself is applied from Result.vue so the keyframe and the base state read
           the same --result-check-len. -->
      <svg
        v-if="drawsCheck"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path data-result-draw d="M5 13l4.5 4.5L19 7" />
      </svg>
      <Icon v-else :name="glyph" aria-hidden="true" />
    </slot>
  </div>
</template>
