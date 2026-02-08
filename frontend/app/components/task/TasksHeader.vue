<template>
  <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
    <div class="flex shrink-0 items-center gap-x-2.5">
      <Icon :name="icon" class="size-5 sm:size-6" />
      <h1 class="page-title">{{ title }}</h1>
    </div>

    <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    required: true,
  },
  icon: {
    type: String,
    default: "hugeicons:task-daily-01",
  },
});
</script>
