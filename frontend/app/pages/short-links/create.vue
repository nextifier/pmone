<template>
  <div class="mx-auto max-w-xl space-y-9 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-6">
      <BackButton destination="/short-links" />
      <h1 class="page-title">Create Short Link</h1>
    </div>

    <FormShortLink ref="formRef" mode="create" />
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("", {
  title: "Create Short Link",
  description: "Create a new short link",
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
