<template>
  <div class="mx-auto max-w-xl space-y-9 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-6">
      <ButtonBack destination="/forms" force-destination />
      <h1 class="page-title">Create form</h1>
    </div>

    <FormBuilderTemplatePicker
      v-model="templateKey"
      @loaded="templates = $event"
    />

    <FormForm ref="formRef" mode="create" :template="templateKey" />
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["forms.create"],
  layout: "app",
});

usePageMeta(null, {
  title: "Create Form",
  description: "Create a new form.",
});

const formRef = ref(null);
const templateKey = ref(null);
const templates = ref([]);

/**
 * The key rides along to the backend, which seeds the fields; the title and
 * description are filled in here so the author sees what they picked before
 * they submit.
 */
watch(templateKey, (key) => {
  const template = templates.value.find((item) => item.key === key);
  formRef.value?.applyTemplate(template ?? { title: "", description: "" });
});

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => {
      formRef.value?.handleSubmit();
    },
  },
});
</script>
