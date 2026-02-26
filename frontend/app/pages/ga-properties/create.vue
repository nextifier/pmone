<template>
  <div class="mx-auto max-w-xl space-y-9 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-6">
      <BackButton destination="/ga-properties" />
      <h1 class="page-title">Create GA4 Property</h1>
    </div>

    <FormGaProperty ref="formRef" mode="create" />
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth", "role"],
  roles: ["master"],
  layout: "app",
});

usePageMeta(null, {
  title: "Create Google Analytics Property",
  description: "Add a new Google Analytics 4 property",
});

const formRef = ref(null);
</script>
