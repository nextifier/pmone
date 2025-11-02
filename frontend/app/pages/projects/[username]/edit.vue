<template>
  <div class="min-h-screen-offset mx-auto max-w-xl">
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

usePageMeta("", {
  title: title,
  description: description,
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
