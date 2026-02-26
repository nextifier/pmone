<template>
  <div class="mx-auto max-w-xl space-y-9 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-6">
      <BackButton destination="/permissions" />
      <h1 class="page-title">Create Permission</h1>
    </div>

    <FormPermission ref="formRef" mode="create" />
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["permissions.create"],
  layout: "app",
});

usePageMeta(null, {
  title: "Create Permission",
  description: "Create a new permission",
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
