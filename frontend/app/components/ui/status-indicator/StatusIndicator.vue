<template>
  <div v-if="status" class="flex items-center gap-x-1.5">
    <span
      class="relative z-10 inline-flex size-2 rounded-full before:z-0 before:size-full before:animate-[ping_4s_cubic-bezier(0,0,0.2,1)_infinite] before:rounded-full before:opacity-75"
      :class="{
        'bg-green-500 before:bg-green-500': ['available'].includes(
          status.toLocaleLowerCase(),
        ),
        'bg-yellow-400 before:bg-yellow-400': [
          'coming soon',
          'upcoming',
          'maintenance',
          'sedang dalam perbaikan',
        ].includes(status.toLocaleLowerCase()),
        'bg-red-500 before:bg-red-500': ['sold out', 'unavailable'].includes(
          status.toLocaleLowerCase(),
        ),
      }"
    ></span>
    <span v-if="showStatusLabel" class="tracking-tight">{{ status }}</span>
  </div>
</template>

<script setup>
const props = defineProps({
  status: {
    type: String,
    required: true,
  },
  showStatusLabel: {
    type: Boolean,
    default: true,
  },
});
</script>
