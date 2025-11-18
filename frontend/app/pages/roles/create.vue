<template>
  <div class="mx-auto max-w-xl space-y-9 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-6">
      <BackButton destination="/roles" />
      <h1 class="page-title">Create Role</h1>
    </div>

    <FormRole ref="formRef" mode="create" />
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("", {
  title: "Create Role",
  description: "Create a new role",
});

const { user } = useSanctumAuth();

// Check if user has master role
if (!user.value?.roles?.includes("master")) {
  navigateTo("/dashboard");
}

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
