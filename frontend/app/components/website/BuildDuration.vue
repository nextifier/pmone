<template>
  <span
    class="tabular-nums tracking-tight"
    :class="[running ? 'text-info-foreground' : '', large ? 'text-lg font-semibold tracking-tighter' : 'text-sm']"
  >
    {{ formatDuration(seconds) || "—" }}
  </span>
</template>

<script setup>
import { useIntervalFn } from "@vueuse/core";

const props = defineProps({
  build: { type: Object, default: null },
  /** Card-sized rather than table-cell-sized. */
  large: { type: Boolean, default: false },
});

/**
 * A running build counts up, and it does so in here rather than in the table
 * cell that renders it — reading a clock ref inside a TanStack `cell()` would
 * re-render every row of the table once a second.
 */
const running = computed(() => isBuildRunning(props.build));
const now = ref(Date.now());

const { pause, resume } = useIntervalFn(() => (now.value = Date.now()), 1000, {
  immediate: false,
});

watchEffect(() => (running.value ? resume() : pause()));

const seconds = computed(() => buildElapsed(props.build, now.value));
</script>
