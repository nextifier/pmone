<template>
  <!--
    Business categories stay outside the panel: they are a taxonomy, not fields
    on the brand form, so previewing them beside it would be a lie. Only the
    custom fields feed the preview.
  -->
  <CustomFieldPreviewPanel
    :fields="previewFields"
    value-key="key"
    preview-title="Brand profile fields"
    empty-message="Add a field to see what brands are asked for on their profile."
  >
    <div class="flex flex-col gap-y-5">
      <h2 class="page-title">Brand Field Settings</h2>

      <ProjectCustomFieldsManager
        :project-username="route.params.username"
        @update:fields="previewFields = $event"
      />
      <ProjectBusinessCategoriesManager :project-username="route.params.username" />
    </div>
  </CustomFieldPreviewPanel>
</template>

<script setup>
/** Opts out of the settings shell's `max-w-2xl`: the preview needs the column. */
definePageMeta({ wideSettings: true });

const props = defineProps({
  project: Object,
});

const route = useRoute();

/** Streamed out of the manager so the pane beside it can render them. */
const previewFields = ref([]);

usePageMeta(null, {
  title: computed(() => `Brand Fields · ${props.project?.name || ""}`),
});
</script>
