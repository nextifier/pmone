<template>
  <div
    class="line-clamp-1 flex items-center gap-x-1.5 rounded-full border border-border px-2.5 py-1.5 text-xs tracking-tight text-primary sm:text-sm"
  >
    <Icon :name="props.iconName" class="size-4 shrink-0 sm:size-5" />
    <span>{{ props.label }}</span>
  </div>
</template>

<script setup>
const props = defineProps({
  label: {
    type: String,
  },
  iconName: {
    type: String,
  },
});
</script>
