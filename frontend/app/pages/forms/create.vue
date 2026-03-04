<template>
  <div class="mx-auto max-w-xl space-y-9 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-6">
      <BackButton destination="/forms" />
      <h1 class="page-title">Create Form</h1>
    </div>

    <FormForm ref="formRef" mode="create" />
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
  description: "Create a new form",
});

const formRef = ref(null);

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => {
      formRef.value?.handleSubmit();
    },
  },
});
</script>
