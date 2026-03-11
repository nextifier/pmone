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
                ? 'bg-muted text-foreground'
                : step.completed
                  ? 'bg-primary text-primary-foreground'
                  : step.current
                    ? 'bg-primary text-primary-foreground'
                    : 'border-border text-muted-foreground border',
            ]"
          >
            <Icon v-if="step.locked" name="hugeicons:square-lock-password" class="size-4" />
            <Icon v-else-if="step.completed" name="lucide:check" class="size-4" />
            <span v-else>{{ i + 1 }}</span>
          </div>
          <span
            :class="[
              'text-sm font-medium tracking-tight transition-colors',
              step.current
                ? 'text-foreground'
                : step.completed
                  ? 'text-foreground'
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
            steps[i + 1].locked ? 'bg-muted' : step.completed ? 'bg-primary/30' : 'bg-border',
          ]"
        />
      </template>
    </div>
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
