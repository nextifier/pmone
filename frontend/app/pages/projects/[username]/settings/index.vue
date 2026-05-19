<template>
  <div class="flex flex-col gap-y-5">
    <template v-if="settingsProject">
      <div class="flex w-full items-start justify-between">
        <h2 class="page-title">General</h2>

        <Button size="sm" :disabled="loading" @click="formProjectRef?.handleSubmit()">
          <Spinner v-if="loading" />
          Save
          <KbdGroup>
            <Kbd>{{ metaSymbol }}</Kbd>
            <Kbd>S</Kbd>
          </KbdGroup>
        </Button>
      </div>

      <FormProject
        ref="formProjectRef"
        :initial-data="settingsProject"
        :eligible-members="eligibleMembers"
        :loading="loading"
        :errors="errors"
        :is-create="false"
        :hide-contact-form="true"
        submit-text="Update Project"
        submit-loading-text="Updating.."
        @submit="updateProject"
      />

      <div
        class="*:bg-muted text-muted-foreground mt-20 flex flex-wrap gap-x-2 gap-y-2.5 text-sm tracking-tight *:rounded-md *:px-2 *:py-1"
      >
        <span
          >ID: <span class="text-foreground">{{ settingsProject.id }}</span></span
        >
        <span
          >ULID: <span class="text-foreground">{{ settingsProject.ulid }}</span></span
        >
        <span
          >Created:
          <span class="text-foreground">{{
            $dayjs(settingsProject.created_at).format("MMM D, YYYY [at] h:mm A")
          }}</span></span
        >
      </div>

      <!-- Danger Zone -->
      <div v-if="canDeleteProject(settingsProject)" class="frame border-destructive/30 mt-6">
        <div class="frame-header">
          <div class="frame-title text-destructive">Danger Zone</div>
        </div>
        <div class="frame-panel">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium tracking-tight">Delete Project</p>
              <p class="text-muted-foreground text-xs tracking-tight text-balance sm:text-sm">
                Move this project to trash. It can be restored later.
              </p>
            </div>
            <button
              type="button"
              :disabled="deleteLoading"
              @click="deleteDialogOpen = true"
              class="bg-destructive hover:bg-destructive/80 flex shrink-0 items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white transition disabled:opacity-50"
            >
              <Spinner v-if="deleteLoading" />
              {{ deleteLoading ? "Deleting.." : "Delete Project" }}
            </button>
          </div>
        </div>
      </div>

      <!-- Delete Confirmation Dialog -->
      <DialogResponsive v-model:open="deleteDialogOpen">
        <template #default>
          <div class="px-4 pb-10 md:px-6 md:py-5">
            <div class="text-primary text-lg font-semibold tracking-tight">Are you sure?</div>
            <p class="text-body mt-1.5 text-sm tracking-tight">
              This will move <strong>{{ settingsProject.name }}</strong> to trash. It can be
              restored later.
            </p>
            <div class="mt-3 flex justify-end gap-2">
              <button
                class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                :disabled="deleteLoading"
                @click="deleteDialogOpen = false"
              >
                Cancel
              </button>
              <button
                class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="deleteLoading"
                @click="handleDelete"
              >
                <Spinner v-if="deleteLoading" class="size-4 text-white" />
                <span v-else>Delete Project</span>
              </button>
            </div>
          </div>
        </template>
      </DialogResponsive>
    </template>

    <template v-else>
      <div v-if="settingsLoading" class="flex items-center justify-center py-20">
        <div class="flex items-center gap-x-2">
          <Spinner class="size-4 shrink-0" />
          <span class="text-base tracking-tight">Loading</span>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

const props = defineProps({
  project: Object,
});

const route = useRoute();
const router = useRouter();
const sanctumFetch = useSanctumClient();
const { $dayjs } = useNuxtApp();
const { signalRefresh } = useDataRefresh();

const formProjectRef = ref(null);
const { metaSymbol } = useShortcuts();

const loading = ref(false);
const errors = ref({});
const deleteLoading = ref(false);
const deleteDialogOpen = ref(false);

const { isAdminOrMaster: canEditprojects, canDeleteProject } = usePermission();

const { data: projectResponse, pending: settingsLoading } = await useLazySanctumFetch(
  () => `/api/projects/${route.params.username}`,
  {
    key: `project-settings-${route.params.username}`,
  }
);

const settingsProject = computed(() => projectResponse.value?.data || null);

const { data: eligibleMembersResponse } = await useLazySanctumFetch(
  "/api/projects/eligible-members",
  {
    key: "projects-eligible-members",
  }
);

const eligibleMembers = computed(() => eligibleMembersResponse.value?.data || []);

async function updateProject(payload) {
  loading.value = true;
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

    const response = await sanctumFetch(`/api/projects/${settingsProject.value.username}`, {
      method: "PUT",
      body: payload,
    });

    if (response.data) {
      toast.success("Project updated successfully!");
      signalRefresh("projects-list");
      navigateTo(`/projects/${response.data.username}/settings`);
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
  } finally {
    loading.value = false;
  }
}

async function handleDelete() {
  deleteLoading.value = true;

  try {
    await sanctumFetch(`/api/projects/${settingsProject.value.username}`, {
      method: "DELETE",
    });

    toast.success("Project moved to trash");
    signalRefresh("projects-list");
    deleteDialogOpen.value = false;
    router.push("/projects");
  } catch (error) {
    toast.error(error.response?._data?.message || "Failed to delete project");
  } finally {
    deleteLoading.value = false;
  }
}

usePageMeta(null, {
  title: computed(() => `General · ${props.project?.name || ""}`),
});
</script>
