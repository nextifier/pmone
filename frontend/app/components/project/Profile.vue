<template>
  <div v-if="project" class="flex items-center gap-x-1.5 tracking-tight">
    <Avatar :model="project" class="size-8" />
    <div class="grid flex-1 text-left text-sm leading-tight">
      <span class="inline-flex items-center gap-x-[3px] truncate align-middle font-medium">
        <span class="text-foreground shrink truncate">{{ project?.name }}</span>
      </span>
      <!-- <span class="text-muted-foreground truncate text-xs">{{ project.username }}</span> -->
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  project: Object,
});
</script>
