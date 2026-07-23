<script setup lang="ts">
import { computed } from "vue";

const props = withDefaults(
  defineProps<{
    value?: number;
    min?: number;
    max?: number;
    size?: number;
    strokeWidth?: number;
  }>(),
  { value: 0, min: 0, max: 1, size: 18, strokeWidth: 1.5 },
);

const clamped = computed(() =>
  Math.min(Math.max(props.value, props.min), props.max),
);
const radius = computed(() => props.size / 2 - props.strokeWidth);
const circumference = computed(() => 2 * Math.PI * radius.value);
const progress = computed(
  () => (clamped.value / props.max) * circumference.value,
);
const center = computed(() => props.size / 2);
</script>

<template>
  <!-- `.attr` on viewBox/cx/cy/r/transform: these names also exist as read-only
       properties on SVG elements, and Vue's hydration path patches dynamic props
       without the SVG namespace, so it would try `el.cx = …` and warn. The
       modifier forces setAttribute. -->
  <svg
    role="progressbar"
    :viewBox.attr="`0 0 ${size} ${size}`"
    :aria-valuenow="clamped"
    :aria-valuemin="min"
    :aria-valuemax="max"
    :style="{ width: `${size}px`, height: `${size}px` }"
  >
    <circle
      :cx.attr="center"
      :cy.attr="center"
      :r.attr="radius"
      fill="none"
      :stroke-width="strokeWidth"
      class="stroke-current/25"
    />
    <circle
      :cx.attr="center"
      :cy.attr="center"
      :r.attr="radius"
      fill="none"
      :stroke-width="strokeWidth"
      stroke="currentColor"
      :stroke-dasharray="circumference"
      :stroke-dashoffset="circumference - progress"
      stroke-linecap="round"
      :transform.attr="`rotate(-90 ${center} ${center})`"
      class="transition-all"
    />
  </svg>
</template>
