<template>
  <div
    :class="[
      'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
      locked
        ? 'bg-muted text-muted-foreground'
        : completed
          ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
          : 'border-primary/50 text-primary border bg-transparent',
    ]"
  >
    <Icon v-if="locked" name="hugeicons:lock-01" class="size-3" />
    <Icon v-else-if="completed" name="hugeicons:checkmark-circle-02" class="size-3.5" />
    <span v-else>{{ step }}</span>
  </div>
</template>

<script setup>
defineProps({
  step: { type: Number, required: true },
  completed: { type: Boolean, default: false },
  locked: { type: Boolean, default: false },
});
</script>
