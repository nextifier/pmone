<template>
  <div class="flex flex-col gap-y-0.5 overflow-hidden">
    <button
      @click="$emit('edit', permission)"
      class="text-foreground inline text-left text-sm font-medium tracking-tight hover:underline"
    >
      {{ permission.name }}
    </button>

    <div class="text-muted-foreground line-clamp-1 inline-flex items-center gap-x-1">
      <span class="text-xs tracking-tight">
        {{ permission.roles?.length || 0 }} {{ permission.roles?.length === 1 ? 'role' : 'roles' }}
      </span>
    </div>
  </div>
</template>

<script setup>
defineProps({
  permission: {
    type: Object,
    required: true,
  },
});

defineEmits(['edit']);
</script>
