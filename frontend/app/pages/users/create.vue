<template>
  <div class="mx-auto flex max-w-xl flex-col gap-y-5 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-5">
      <BackButton destination="/users" />

      <h1 class="page-title">Create New User</h1>
    </div>

    <FormUser
      :roles="roles"
      :loading="loading"
      :errors="errors"
      :is-create="true"
      :show-password="true"
      :show-account-settings="true"
      :show-roles="true"
      :show-images="true"
      submit-text="Create User"
      submit-loading-text="Creating.."
      @submit="createUser"
    />
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "role"],
  roles: ["admin", "master"],
  layout: "app",
});

const title = "Create New User";
const description = "";

usePageMeta("", {
  title: title,
  description: description,
});

const sanctumFetch = useSanctumClient();
const { signalRefresh } = useDataRefresh();

// State
const loading = ref(false);
const error = ref(null);
const success = ref(null);
const errors = ref({});

// Fetch roles with lazy loading
const { data: rolesResponse } = await useLazySanctumFetch("/api/users/roles", {
  key: "users-roles",
});

const roles = computed(() => rolesResponse.value?.data || []);

// Create user
async function createUser(payload) {
  loading.value = true;
  error.value = null;
  success.value = null;
  errors.value = {};

  try {
    const response = await sanctumFetch("/api/users", {
      method: "POST",
      body: payload,
    });

    if (response.data) {
      toast.success(`User "${response.data.name}" created successfully!`);

      // Signal that users list needs refresh
      signalRefresh("users-list");

      // Navigate to users list
      navigateTo("/users");
    }
  } catch (err) {
    if (err.response?.status === 422 && err.response?._data?.errors) {
      errors.value = err.response._data.errors;
      const firstErrorField = Object.keys(err.response._data.errors)[0];
      const firstErrorMessage = err.response._data.errors[firstErrorField][0];
      toast.error(firstErrorMessage || "Please fix the validation errors.");
    } else {
      const errorMessage = err.response?._data?.message || err.message || "Failed to create user";
      toast.error(errorMessage);
    }
    console.error("Error creating user:", err);
  } finally {
    loading.value = false;
  }
}
</script>
