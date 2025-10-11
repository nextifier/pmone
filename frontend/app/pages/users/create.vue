<template>
  <div class="mx-auto max-w-md space-y-6">
    <div class="flex items-center gap-x-4">
      <BackButton :showLabel="false" destination="/users" />

      <h1 class="page-title">Create New User</h1>
    </div>

    <!-- Error message -->
    <div
      v-if="error"
      class="border-destructive bg-destructive/10 text-destructive rounded-lg border p-4"
    >
      {{ error }}
    </div>

    <!-- Success message -->
    <div
      v-if="success"
      class="rounded-lg border border-green-500 bg-green-100 p-4 text-green-800 dark:bg-green-900 dark:text-green-300"
    >
      {{ success }}
    </div>

    <FormProfile
      :roles="roles"
      :loading="loading"
      :errors="errors"
      :is-create="true"
      :show-password="true"
      :show-account-settings="true"
      :show-roles="true"
      :show-images="true"
      :show-reset="true"
      submit-text="Create User"
      submit-loading-text="Creating.."
      @submit="createUser"
      @reset="resetForm"
    />
  </div>
</template>

<script setup>
import FormProfile from "@/components/FormProfile.vue";

definePageMeta({
  middleware: ["sanctum:auth", "admin-master"],
  layout: "app",
});

const title = "Create New User";
const description = "";
const route = useRoute();

useSeoMeta({
  titleTemplate: "%s · %siteName",
  title: title,
  ogTitle: title,
  description: description,
  ogDescription: description,
  ogUrl: useAppConfig().app.url + route.fullPath,
  twitterCard: "summary_large_image",
});

const sanctumFetch = useSanctumClient();

// State
const loading = ref(false);
const error = ref(null);
const success = ref(null);
const errors = ref({});
const roles = ref([]);

// Load roles
async function loadRoles() {
  try {
    const response = await sanctumFetch("/api/users/roles");
    roles.value = response.data;
  } catch (err) {
    console.error("Error loading roles:", err);
  }
}

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
      success.value = `User "${response.data.name}" created successfully!`;

      // Reset form after successful creation
      setTimeout(() => {
        navigateTo("/users");
      }, 2000);
    }
  } catch (err) {
    if (err.response?.status === 422 && err.response?._data?.errors) {
      errors.value = err.response._data.errors;
      error.value = "Please fix the validation errors below.";
    } else {
      error.value = err.message || "Failed to create user";
    }
    console.error("Error creating user:", err);
  } finally {
    loading.value = false;
  }
}

// Reset form
function resetForm() {
  errors.value = {};
  error.value = null;
  success.value = null;
}

// Load data on mount
onMounted(async () => {
  await loadRoles();
});
</script>
