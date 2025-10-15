<template>
  <div class="mx-auto max-w-md space-y-8">
    <div class="flex flex-col items-start gap-y-5">
      <BackButton destination="/users" />

      <h1 class="page-title">Create New User</h1>
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
      submit-text="Create User"
      submit-loading-text="Creating.."
      @submit="createUser"
    />
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";
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
      toast.success(`User "${response.data.name}" created successfully!`);

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

// Load data on mount
onMounted(async () => {
  await loadRoles();
});
</script>
