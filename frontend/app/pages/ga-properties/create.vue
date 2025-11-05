<template>
  <div class="mx-auto max-w-xl space-y-9">
    <div class="flex flex-col items-start gap-y-6">
      <BackButton destination="/ga-properties" />
      <h1 class="page-title">Create GA4 Property</h1>
    </div>

    <FormGaProperty ref="formRef" mode="create" />
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("", {
  title: "Create GA4 Property",
  description: "Add a new Google Analytics 4 property",
});

const formRef = ref(null);

defineShortcuts({
  meta_s: {
    handler: () => {
      formRef.value?.handleSubmit();
    },
  },
});
</script>
