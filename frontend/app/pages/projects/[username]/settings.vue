<template>
  <div class="flex flex-col gap-y-5">
    <template v-if="project">
      <div class="flex w-full items-center justify-between">
        <h2 class="text-lg font-semibold tracking-tight">Settings</h2>

        <button
          @click="formProjectRef?.handleSubmit()"
          :disabled="loading"
          class="text-primary-foreground hover:bg-primary/80 bg-primary flex items-center justify-center gap-x-1 rounded-lg px-3 py-1.5 text-sm font-medium tracking-tight transition active:scale-98 disabled:opacity-50"
        >
          <Spinner v-if="loading" />
          <span>Save</span>
        </button>
      </div>

      <FormProject
        ref="formProjectRef"
        :initial-data="project"
        :eligible-members="eligibleMembers"
        :loading="loading"
        :errors="errors"
        :is-create="false"
        submit-text="Update Project"
        submit-loading-text="Updating.."
        @submit="updateProject"
      />

      <ProjectCustomFieldsManager :project-username="route.params.username" />

      <div
        class="*:bg-muted text-muted-foreground mt-20 flex flex-wrap gap-x-2 gap-y-2.5 text-sm tracking-tight *:rounded-md *:px-2 *:py-1"
      >
        <span
          >ID: <span class="text-foreground">{{ project.id }}</span></span
        >
        <span
          >ULID: <span class="text-foreground">{{ project.ulid }}</span></span
        >
        <span
          >Created:
          <span class="text-foreground">{{
            $dayjs(project.created_at).format("MMM D, YYYY [at] h:mm A")
          }}</span></span
        >
      </div>
    </template>

    <template v-else>
      <LoadingState v-if="initialLoading" />
      <div v-else-if="error" class="flex w-full items-center justify-center py-20">
        <div class="flex flex-col items-center justify-center gap-y-4 text-center">
          <div class="space-y-1">
            <h3 class="text-lg font-semibold tracking-tighter">{{ error }}</h3>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["permission"],
  permissions: ["projects.update"],
});

const route = useRoute();
const { user: currentUser } = useSanctumAuth();
const sanctumFetch = useSanctumClient();
const { $dayjs } = useNuxtApp();
const { signalRefresh } = useDataRefresh();

const formProjectRef = ref(null);

const loading = ref(false);
const deleting = ref(false);
const success = ref(null);
const errors = ref({});

const {
  isAdminOrMaster: canEditprojects,
  isAdminOrMaster: canDeleteprojects,
  isMaster,
} = usePermission();

const {
  data: projectResponse,
  pending: initialLoading,
  error: projectError,
} = await useLazySanctumFetch(() => `/api/projects/${route.params.username}`, {
  key: `project-settings-${route.params.username}`,
});

const project = computed(() => projectResponse.value?.data || null);

usePageMeta(null, {
  title: computed(() => `Settings · ${project.value?.name || ""}`),
});

const error = computed(() => {
  if (!projectError.value) return null;

  const err = projectError.value;
  if (err.statusCode === 404) {
    return "Project not found";
  } else if (err.statusCode === 403) {
    return "You do not have permission to view this project";
  }
  return err.message || "Failed to load project";
});

const { data: eligibleMembersResponse } = await useLazySanctumFetch(
  "/api/projects/eligible-members",
  {
    key: "projects-eligible-members",
  }
);

const eligibleMembers = computed(() => eligibleMembersResponse.value?.data || []);

async function updateProject(payload) {
  loading.value = true;
  error.value = null;
  success.value = null;
  errors.value = {};

  try {
    if (!canEditprojects.value) {
      const allowedFields = [
        "name",
        "username",
        "email",
        "phone",
        "birth_date",
        "gender",
        "bio",
        "visibility",
        "tmp_profile_image",
        "tmp_cover_image",
      ];
      Object.keys(payload).forEach((key) => {
        if (!allowedFields.includes(key)) {
          delete payload[key];
        }
      });
    }

    const response = await sanctumFetch(`/api/projects/${project.value.username}`, {
      method: "PUT",
      body: payload,
    });

    if (response.data) {
      toast.success("Project updated successfully!");
      signalRefresh("projects-list");
      navigateTo(`/projects/${response.data.username}`);
    }
  } catch (err) {
    if (err.response?.status === 422 && err.response?._data?.errors) {
      errors.value = err.response._data.errors;
      const firstErrorField = Object.keys(err.response._data.errors)[0];
      const firstErrorMessage = err.response._data.errors[firstErrorField][0];
      toast.error(firstErrorMessage || "Please fix the validation errors.");
    } else {
      const errorMessage =
        err.response?._data?.message || err.message || "Failed to update project";
      toast.error(errorMessage);
    }
    console.error("Error updating project:", err);
  } finally {
    loading.value = false;
  }
}

async function confirmDeleteProject() {
  if (
    !confirm(`Are you sure you want to delete ${project.value.name}? This action cannot be undone.`)
  ) {
    return;
  }

  deleting.value = true;
  error.value = null;

  try {
    await sanctumFetch(`/api/projects/${project.value.username}`, {
      method: "DELETE",
    });

    signalRefresh("projects-list");
    navigateTo("/projects");
  } catch (err) {
    error.value = err.message || "Failed to delete project";
    console.error("Error deleting project:", err);
  } finally {
    deleting.value = false;
  }
}
</script>
