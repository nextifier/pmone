<template>
  <div class="flex flex-col gap-y-0.5 overflow-hidden">
    <NuxtLink
      :to="`/permissions/${permission.id}/edit`"
      class="text-foreground inline text-sm font-medium tracking-tight"
    >
      {{ permission.name }}
    </NuxtLink>

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
</script>
