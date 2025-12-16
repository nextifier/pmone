<template>
  <div class="flex grow items-center justify-center p-6">
    <div class="flex items-center gap-2">
      <Spinner class="size-5 shrink-0" />
      <span class="text-sm tracking-tight">{{ label }}</span>
    </div>
  </div>
</template>

<script setup>
defineProps({
  label: {
    type: String,
    default: "Loading..",
  },
});
</script>
