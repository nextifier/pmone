<template>
  <!-- Desktop: Horizontal -->
  <div class="hidden sm:block">
    <div class="flex items-center">
      <template v-for="(step, i) in steps" :key="step.key">
        <!-- Step -->
        <button
          :disabled="step.locked"
          class="group flex items-center gap-2"
          :class="step.locked ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'"
          @click="!step.locked && $emit('jump', step.key)"
        >
          <div
            :class="[
              'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-medium tracking-tight transition-colors sm:text-sm',
              step.locked
                ? 'bg-muted text-muted-foreground'
                : step.completed
                  ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                  : step.current
                    ? 'bg-primary text-primary-foreground'
                    : 'border-border text-muted-foreground border',
            ]"
          >
            <Icon v-if="step.locked" name="hugeicons:lock-01" class="size-3" />
            <Icon v-else-if="step.completed" name="hugeicons:tick-02" class="size-3.5" />
            <span v-else>{{ i + 1 }}</span>
          </div>
          <span
            :class="[
              'text-xs font-medium tracking-tight transition-colors sm:text-sm',
              step.current
                ? 'text-foreground'
                : step.completed
                  ? 'text-green-700 dark:text-green-400'
                  : 'text-muted-foreground',
              !step.locked && 'group-hover:text-foreground',
            ]"
          >
            {{ step.label }}
          </span>
        </button>
        <!-- Separator -->
        <div
          v-if="i < steps.length - 1"
          :class="[
            'mx-2 h-px flex-1',
            steps[i + 1].locked
              ? 'bg-muted'
              : step.completed
                ? 'bg-green-200 dark:bg-green-900/40'
                : 'bg-border',
          ]"
        />
      </template>
    </div>
  </div>

  <!-- Mobile: Compact dots -->
  <div class="flex items-center justify-center gap-1.5 sm:hidden">
    <button
      v-for="(step, i) in steps"
      :key="step.key"
      :disabled="step.locked"
      class="group"
      @click="!step.locked && $emit('jump', step.key)"
    >
      <div
        :class="[
          'size-2.5 rounded-full transition-colors',
          step.locked
            ? 'bg-muted'
            : step.completed
              ? 'bg-green-500'
              : step.current
                ? 'bg-primary'
                : 'bg-border',
        ]"
      />
    </button>
    <span class="text-muted-foreground ml-2 text-xs tracking-tight">
      {{ completedCount }}/{{ steps.length }}
    </span>
  </div>
</template>

<script setup>
const props = defineProps({
  steps: {
    type: Array,
    required: true,
    // Each: { key, label, completed, current, locked }
  },
});

defineEmits(["jump"]);

const completedCount = computed(() => props.steps.filter((s) => s.completed).length);
</script>
