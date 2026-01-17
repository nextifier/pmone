<template>
  <div class="flex items-center gap-x-2 select-none">
    <span class="text-muted-foreground text-xs tracking-tight">{{ label }}</span>
    <div class="flex items-end gap-px">
      <span
        v-for="(_, index) in 3"
        :key="index"
        class="w-1.5 rounded-xs"
        :class="[
          barHeightClass(index),
          getBarClass(index)
        ]"
      ></span>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  level: {
    type: String,
    default: null,
    validator: (value) => ['low', 'medium', 'high'].includes(value?.toLowerCase()) || value === null
  },
  label: {
    type: String,
    default: 'Priority'
  }
});

const barHeightClass = (index) => {
  if (index === 0) return 'h-1.5';
  if (index === 1) return 'h-2';
  return 'h-2.5';
};

const getBarClass = (index) => {
  if (!props.level) return 'bg-primary/20';

  const level = props.level.toLowerCase();

  if (level === 'low') {
    return index === 0 ? 'bg-green-500' : 'bg-primary/20';
  }

  if (level === 'medium') {
    return index <= 1 ? 'bg-yellow-500' : 'bg-primary/20';
  }

  if (level === 'high') {
    return 'bg-red-500';
  }

  return 'bg-primary/20';
};
</script>
