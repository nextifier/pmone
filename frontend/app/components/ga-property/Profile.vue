<template>
  <div v-if="model" class="flex items-center gap-x-1.5 tracking-tight">
    <Avatar :model="model" class="size-8" />
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
</script>
