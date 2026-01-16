<template>
  <div class="flex items-center gap-x-2 select-none">
    <span class="text-muted-foreground text-xs tracking-tight">{{ label }}</span>
    <div class="flex items-end gap-px">
      <span
        v-for="(_, index) in 3"
        :key="index"
        class="bg-primary/20 w-1.5 rounded-xs"
        :class="[
          barHeightClass(index),
          getActiveClass(index)
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

const getActiveClass = (index) => {
  if (!props.level) return '';

  const level = props.level.toLowerCase();

  if (level === 'low' && index === 0) {
    return 'bg-green-500';
  }

  if (level === 'medium' && index <= 1) {
    return 'bg-yellow-500';
  }

  if (level === 'high') {
    return 'bg-red-500';
  }

  return '';
};
</script>
