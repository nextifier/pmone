<script setup lang="ts">
import { computed } from "vue";

const props = withDefaults(
  defineProps<{
    value?: number;
    size?: number;
    strokeWidth?: number;
    showLabel?: boolean;
  }>(),
  {
    value: 0,
    size: 36,
    strokeWidth: 4,
    showLabel: true,
  }
);

const clamped = computed(() => Math.max(0, Math.min(100, Math.round(props.value || 0))));
const center = computed(() => props.size / 2);
const radius = computed(() => (props.size - props.strokeWidth) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const dashOffset = computed(() => circumference.value * (1 - clamped.value / 100));

const colorClass = computed(() =>
  clamped.value < 34 ? "text-red-500" : clamped.value < 67 ? "text-yellow-400" : "text-green-500"
);

const textColorClass = computed(() =>
  clamped.value < 34
    ? "text-destructive-foreground"
    : clamped.value < 67
      ? "text-warning-foreground"
      : "text-success-foreground"
);
</script>

<template>
  <div
    class="relative inline-flex shrink-0 items-center justify-center"
    :style="{ width: `${size}px`, height: `${size}px` }"
    role="img"
    :aria-label="`${clamped}%`"
  >
    <svg :width="size" :height="size" class="-rotate-90" aria-hidden="true">
      <circle
        :cx="center"
        :cy="center"
        :r="radius"
        fill="none"
        :stroke-width="strokeWidth"
        stroke="currentColor"
        class="text-muted"
      />
      <circle
        :cx="center"
        :cy="center"
        :r="radius"
        fill="none"
        :stroke-width="strokeWidth"
        stroke="currentColor"
        stroke-linecap="round"
        :stroke-dasharray="circumference"
        :stroke-dashoffset="dashOffset"
        :class="colorClass"
        class="transition-[stroke-dashoffset] duration-500 ease-out"
      />
    </svg>
    <span
      v-if="showLabel"
      class="absolute inset-0 flex items-center justify-center text-[10px] font-medium tracking-tight tabular-nums"
      :class="textColorClass"
    >
      {{ clamped }}
    </span>
  </div>
</template>
