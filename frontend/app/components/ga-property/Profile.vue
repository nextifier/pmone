<template>
  <div v-if="model" class="flex items-center gap-x-1.5 tracking-tight">
    <Avatar :model="avatarModel" class="size-8" />
    <div class="grid flex-1 text-left text-sm leading-tight">
      <span
        class="text-foreground inline-flex items-center gap-x-[3px] truncate align-middle font-medium"
      >
        <span class="shrink truncate">{{ model?.name }}</span>
      </span>
      <span v-if="model?.property_id" class="text-muted-foreground truncate text-xs">{{
        model.property_id
      }}</span>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  model: Object,
});

// Create a model with profile_image from project for Avatar component
const avatarModel = computed(() => {
  if (!props.model) return null;
  return {
    ...props.model,
    profile_image: props.model?.project?.profile_image || null,
  };
});
</script>
