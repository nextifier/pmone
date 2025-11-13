<template>
  <div class="mx-auto max-w-xl space-y-9 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-6">
      <BackButton destination="/projects" />

      <h1 class="page-title">Create New Project</h1>
    </div>

    <FormProject
      :eligible-members="eligibleMembers"
      :loading="loading"
      :errors="errors"
      :is-create="true"
      submit-text="Create Project"
      submit-loading-text="Creating.."
      @submit="createProject"
    />
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "admin-master"],
  layout: "app",
});

const title = "Create New Project";
const description = "";

usePageMeta("", {
  title: title,
  description: description,
});

const sanctumFetch = useSanctumClient();

// State
const loading = ref(false);
const error = ref(null);
const success = ref(null);
const errors = ref({});
const eligibleMembers = ref([]);

// Load eligible members
async function loadEligibleMembers() {
  try {
    const response = await sanctumFetch("/api/projects/eligible-members");
    eligibleMembers.value = response.data;
  } catch (err) {
    console.error("Error loading eligible members:", err);
    toast.error("Failed to load eligible members");
  }
}

// Create Project
async function createProject(payload) {
  loading.value = true;
  error.value = null;
  success.value = null;
  errors.value = {};

  try {
    const response = await sanctumFetch("/api/projects", {
      method: "POST",
      body: payload,
    });

    if (response.data) {
      toast.success(`Project "${response.data.name}" created successfully!`);

      // Navigate to projects list
      navigateTo("/projects");
    }
  } catch (err) {
    if (err.response?.status === 422 && err.response?._data?.errors) {
      errors.value = err.response._data.errors;
      const firstErrorField = Object.keys(err.response._data.errors)[0];
      const firstErrorMessage = err.response._data.errors[firstErrorField][0];
      toast.error(firstErrorMessage || "Please fix the validation errors.");
    } else {
      const errorMessage =
        err.response?._data?.message || err.message || "Failed to create project";
      toast.error(errorMessage);
    }
    console.error("Error creating project:", err);
  } finally {
    loading.value = false;
  }
}

// Load data on mount
onMounted(async () => {
  await loadEligibleMembers();
});
</script>
