<template>
  <div class="mx-auto flex max-w-xl flex-col gap-y-5 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-5">
      <BackButton destination="/exhibitors" />

      <h1 class="page-title">Create New Exhibitor</h1>
    </div>

    <FormUser
      :roles="roles"
      :loading="loading"
      :errors="errors"
      :is-create="true"
      :show-password="true"
      :show-account-settings="true"
      :show-roles="false"
      :show-images="true"
      submit-text="Create Exhibitor"
      submit-loading-text="Creating.."
      @submit="createExhibitor"
    />
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["users.create"],
  layout: "app",
});

usePageMeta(null, {
  title: "Create New Exhibitor",
});

const sanctumFetch = useSanctumClient();
const { signalRefresh } = useDataRefresh();

// State
const loading = ref(false);
const errors = ref({});

// Fetch roles with lazy loading (needed for FormUser internals)
const { data: rolesResponse } = await useLazySanctumFetch("/api/users/roles", {
  key: "users-roles",
});

const roles = computed(() => rolesResponse.value?.data || []);

// Create exhibitor
async function createExhibitor(payload) {
  loading.value = true;
  errors.value = {};

  // Auto-assign exhibitor role
  payload.roles = ["exhibitor"];

  try {
    const response = await sanctumFetch("/api/users", {
      method: "POST",
      body: payload,
    });

    if (response.data) {
      toast.success(`Exhibitor "${response.data.name}" created successfully!`);

      signalRefresh("exhibitors-list");

      navigateTo("/exhibitors");
    }
  } catch (err) {
    if (err.response?.status === 422 && err.response?._data?.errors) {
      errors.value = err.response._data.errors;
      const firstErrorField = Object.keys(err.response._data.errors)[0];
      const firstErrorMessage = err.response._data.errors[firstErrorField][0];
      toast.error(firstErrorMessage || "Please fix the validation errors.");
    } else {
      const errorMessage = err.response?._data?.message || err.message || "Failed to create exhibitor";
      toast.error(errorMessage);
    }
    console.error("Error creating exhibitor:", err);
  } finally {
    loading.value = false;
  }
}
</script>
