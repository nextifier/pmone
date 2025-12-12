<template>
  <div class="min-h-screen-offset mx-auto max-w-xl pt-4 pb-16">
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

        <div v-else-if="error" class="frame w-full">
          <div class="frame-panel">
            <div class="flex w-full flex-col items-center justify-center gap-y-4 text-center">
              <div
                class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
              >
                <div class="translate-y-1.5 -rotate-6">
                  <Icon name="hugeicons:file-empty-01" />
                </div>
                <div>
                  <Icon name="hugeicons:search-remove" />
                </div>
                <div class="translate-y-1.5 rotate-6">
                  <Icon name="hugeicons:user" />
                </div>
              </div>
              <div class="space-y-1">
                <h3 class="text-lg font-semibold tracking-tighter">{{ error }}</h3>
              </div>
              <NuxtLink
                to="/projects/"
                class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              >
                <Icon name="lucide:arrow-left" class="size-4 shrink-0" />
                <span>Back to Projects</span>
              </NuxtLink>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "role"],
  roles: ["admin", "master"],
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
const loading = ref(false);
const deleting = ref(false);
const success = ref(null);
const errors = ref({});

const title = "Edit project";
const description = "";

usePageMeta("", {
  title: title,
  description: description,
});

// Permission checking using composable
const { isAdminOrMaster: canEditprojects, isAdminOrMaster: canDeleteprojects, isMaster } = usePermission();

// Fetch project data with lazy loading
const {
  data: projectResponse,
  pending: initialLoading,
  error: projectError,
} = await useLazySanctumFetch(() => `/api/projects/${route.params.username}`, {
  key: `project-edit-${route.params.username}`,
});

const project = computed(() => projectResponse.value?.data || null);

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

// Fetch eligible members with lazy loading
const {
  data: eligibleMembersResponse,
} = await useLazySanctumFetch("/api/projects/eligible-members", {
  key: "projects-eligible-members",
});

const eligibleMembers = computed(() => eligibleMembersResponse.value?.data || []);

const canDeleteThisproject = computed(() => {
  if (!canDeleteprojects.value) return false;
  if (!project.value) return false;
  // Admin cannot delete master projects
  if (project.value.roles?.includes("master") && !isMaster.value) return false;
  // Cannot delete yourself
  if (project.value.username === currentUser.value?.username) return false;
  return true;
});

// Update project
async function updateproject(payload) {
  loading.value = true;
  error.value = null;
  success.value = null;
  errors.value = {};

  try {
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
</script>
