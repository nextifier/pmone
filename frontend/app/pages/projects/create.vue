<template>
  <div class="mx-auto flex max-w-xl flex-col gap-y-5 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-5">
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
  middleware: ["sanctum:auth", "role"],
  roles: ["admin", "master"],
  layout: "app",
});

const title = "Create New Project";
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

// Fetch eligible members with lazy loading
const { data: eligibleMembersResponse } = await useLazySanctumFetch(
  "/api/projects/eligible-members",
  {
    key: "projects-eligible-members",
  }
);

const eligibleMembers = computed(() => eligibleMembersResponse.value?.data || []);

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

      // Signal that projects list needs refresh
      signalRefresh("projects-list");

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
</script>
