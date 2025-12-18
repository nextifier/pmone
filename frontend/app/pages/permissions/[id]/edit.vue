<template>
  <div class="mx-auto max-w-xl space-y-9 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-6">
      <BackButton destination="/permissions" />
      <h1 class="page-title">Edit Permission</h1>
    </div>

    <div v-if="pending" class="flex items-center gap-2 py-4">
      <Spinner class="size-4" />
      <span class="text-muted-foreground text-sm">Loading permission...</span>
    </div>

    <div
      v-else-if="error"
      class="border-destructive text-destructive rounded-md border p-3 text-sm"
    >
      {{ error?.data?.message || "Failed to load permission" }}
    </div>

    <FormPermission
      v-else-if="permission"
      ref="formRef"
      mode="edit"
      :permission="permission"
    />
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["permissions.update"],
  layout: "app",
});

usePageMeta("", {
  title: "Edit Permission",
  description: "Edit an existing permission",
});

const route = useRoute();
const permissionId = route.params.id;

// Fetch permission data
const { data: permissionResponse, pending, error } = await useLazySanctumFetch(
  `/api/permissions/${permissionId}`,
  {
    key: `permission-${permissionId}`,
  }
);

const permission = computed(() => permissionResponse.value?.data);

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
