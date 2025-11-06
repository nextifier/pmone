<template>
  <div class="flex flex-col gap-y-0.5 overflow-hidden">
    <NuxtLink
      :to="`/roles/${role.name}/edit`"
      class="text-foreground inline text-sm font-medium tracking-tight capitalize"
    >
      {{ role.name }}
    </NuxtLink>

    <div class="text-muted-foreground line-clamp-1 inline-flex items-center gap-x-1">
      <span class="text-xs tracking-tight">
        {{ role.permissions?.length || 0 }} {{ role.permissions?.length === 1 ? 'permission' : 'permissions' }}
      </span>
    </div>
  </div>
</template>

<script setup>
defineProps({
  role: {
    type: Object,
    required: true,
  },
});
</script>
