<template>
  <div class="min-h-screen-offset mx-auto max-w-xl space-y-9">
    <template v-if="project">
      <div class="flex flex-col gap-y-6">
        <div class="flex w-full items-center justify-between">
          <BackButton destination="/projects" />

          <button
            @click="formProjectRef?.handleSubmit()"
            :disabled="loading"
            class="text-primary-foreground hover:bg-primary/80 bg-primary flex items-center justify-center gap-x-1 rounded-lg px-3 py-1.5 text-sm font-medium tracking-tight transition active:scale-98 disabled:opacity-50"
          >
            <Spinner v-if="loading" />
            <span>Save</span>
          </button>
        </div>

        <h1 class="page-title">Edit Project</h1>
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
        @submit="updateproject"
      />

      <div
        v-if="project"
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

      <!-- <div
        v-if="project"
        class="border-border text-foreground w-full overflow-x-scroll rounded-xl border p-4"
      >
        <pre class="text-foreground/80 text-sm !leading-[1.5]">{{ project }}</pre>
      </div> -->
    </template>

    <template v-else>
      <div class="min-h-screen-offset flex w-full items-center justify-center">
        <div v-if="initialLoading" class="flex items-center gap-1.5">
          <Spinner class="size-4 shrink-0" />
          <span class="tracking-tight">Loading</span>
        </div>
        <div v-else-if="error" class="flex flex-col items-center gap-4 text-center">
          <div class="text-muted-foreground flex flex-col gap-2">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="mx-auto size-12"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
              />
            </svg>
            <p class="text-lg font-medium">{{ error }}</p>
          </div>
          <button
            @click="navigateTo('/projects')"
            class="bg-primary text-primary-foreground hover:bg-primary/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight transition"
          >
            Back to Projects
          </button>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "admin-master"],
  layout: "app",
});

const route = useRoute();
const { user: currentUser } = useSanctumAuth();
const sanctumFetch = useSanctumClient();
const { $dayjs } = useNuxtApp();
const { metaSymbol } = useShortcuts();

// Refs
const formProjectRef = ref(null);

// State
const project = ref(null);
const eligibleMembers = ref([]);
const initialLoading = ref(true);
const loading = ref(false);
const deleting = ref(false);
const error = ref(null);
const success = ref(null);
const errors = ref({});

const title = "Edit project";
const description = "";

useSeoMeta({
  titleTemplate: "%s · %siteName",
  title: title,
  ogTitle: title,
  description: description,
  ogDescription: description,
  ogUrl: useAppConfig().app.url + route.fullPath,
  twitterCard: "summary_large_image",
});

// Computed
const canEditprojects = computed(() => {
  return currentUser.value?.roles?.some((role) => ["master", "admin"].includes(role));
});

const canDeleteprojects = computed(() => {
  return currentUser.value?.roles?.some((role) => ["master", "admin"].includes(role));
});

const isMaster = computed(() => {
  return currentUser.value?.roles?.includes("master");
});

const canDeleteThisproject = computed(() => {
  if (!canDeleteprojects.value) return false;
  if (!user.value) return false;
  // Admin cannot delete master projects
  if (user.value.roles?.includes("master") && !isMaster.value) return false;
  // Cannot delete yourself
  if (user.value.username === currentUser.value?.username) return false;
  return true;
});

// Load project data
async function loadproject() {
  initialLoading.value = true;
  error.value = null;

  try {
    const response = await sanctumFetch(`/api/projects/${route.params.username}`);

    if (response.data) {
      project.value = response.data;
    }
  } catch (err) {
    if (err.response?.status === 404) {
      error.value = "Project not found";
    } else if (err.response?.status === 403) {
      error.value = "You do not have permission to view this project";
    } else {
      error.value = err.message || "Failed to load project";
    }
    console.error("Error loading project:", err);
  } finally {
    initialLoading.value = false;
  }
}

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

// Update project
async function updateproject(payload) {
  loading.value = true;
  error.value = null;
  success.value = null;
  errors.value = {};

  try {
    // Remove empty values
    Object.keys(payload).forEach((key) => {
      if (payload[key] === "" || payload[key] === null) {
        delete payload[key];
      }
    });

    // If not admin/master, only allow certain fields
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
        err.response?._data?.message || err.message || "Failed to update project";
      toast.error(errorMessage);
    }
    console.error("Error updating project:", err);
  } finally {
    loading.value = false;
  }
}

// Confirm delete project
async function confirmDeleteproject() {
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

    // Navigate back to projects list
    navigateTo("/projects");
  } catch (err) {
    error.value = err.message || "Failed to delete project";
    console.error("Error deleting project:", err);
  } finally {
    deleting.value = false;
  }
}

// Load data on mount
onMounted(async () => {
  await Promise.all([loadproject(), loadEligibleMembers()]);
});
</script>
