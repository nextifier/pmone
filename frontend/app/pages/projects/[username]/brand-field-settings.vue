<template>
  <div class="flex flex-col gap-y-5">
    <h2 class="text-lg font-semibold tracking-tight">Brand Field Settings</h2>

    <ProjectCustomFieldsManager :project-username="route.params.username" />
    <ProjectBusinessCategoriesManager :project-username="route.params.username" />
  </div>
</template>

<script setup>
const props = defineProps({
  project: Object,
});

const route = useRoute();

definePageMeta({
  middleware: ["permission"],
  permissions: ["projects.update"],
});

usePageMeta(null, {
  title: computed(() => `Brand Field Settings · ${props.project?.name || ""}`),
});
</script>
