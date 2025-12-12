<template>
  <div class="mx-auto max-w-xl space-y-6 pt-4 pb-16">
    <ProjectsHeader title="Project Trash" icon="hugeicons:delete-01">
      <template #actions>
        <nuxt-link
          to="/projects"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:layers-01" class="size-4 shrink-0" />
          <span>All projects</span>
        </nuxt-link>
      </template>
    </ProjectsHeader>

    <ProjectsFilters
      v-model:search-query="searchQuery"
      v-model:selected-statuses="selectedStatuses"
      :status-options="['active', 'draft', 'archived']"
      :pending="pending"
      search-placeholder="Search trashed projects"
      @refresh="refresh"
    >
      <template #actions>
        <button
          v-if="isAdminOrMaster"
          @click="openEmptyTrashDialog"
          :disabled="filteredProjects.length === 0"
          class="bg-destructive/10 hover:bg-destructive/20 text-destructive flex items-center gap-x-1.5 rounded-md px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Icon name="lucide:trash" class="-ml-1 size-4 shrink-0" />
          <span>Empty Trash</span>
        </button>
      </template>
    </ProjectsFilters>

    <ProjectsList
      :projects="filteredProjects"
      :pending="pending"
      :error="error"
      :enable-drag-drop="false"
      :has-active-filters="hasActiveFilters"
      :is-trash="true"
      error-title="Error loading trashed projects"
      empty-title="No trashed projects"
      empty-description="Trashed projects will appear here."
    >
      <template #row-actions="{ project }">
        <button
          @click="openRestoreDialog(project)"
          class="hover:bg-muted hover:text-foreground flex w-full items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
        >
          <Icon name="lucide:undo-2" class="size-4 shrink-0" />
          <span>Restore</span>
        </button>

        <button
          @click="openDeleteDialog(project)"
          class="hover:bg-destructive/10 text-destructive flex w-full items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
        >
          <Icon name="lucide:trash" class="size-4 shrink-0" />
          <span>Delete Permanently</span>
        </button>
      </template>
    </ProjectsList>

    <!-- Restore Dialog -->
    <DialogResponsive v-model:open="restoreDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">Restore Project?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            Are you sure you want to restore <strong>{{ projectToRestore?.name }}</strong
            >?
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <button
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              @click="restoreDialogOpen = false"
            >
              Cancel
            </button>
            <button
              @click="handleRestoreProject"
              :disabled="restoreLoading"
              class="bg-primary text-primary-foreground hover:bg-primary/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Spinner v-if="restoreLoading" class="size-4" />
              <span v-else>Restore</span>
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Delete Dialog -->
    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">
            Delete Project Permanently?
          </div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            Are you sure you want to permanently delete <strong>{{ projectToDelete?.name }}</strong
            >? This action cannot be undone.
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <button
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              @click="deleteDialogOpen = false"
            >
              Cancel
            </button>
            <button
              @click="handleDeleteProject"
              :disabled="deleteLoading"
              class="bg-destructive hover:bg-destructive/90 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Spinner v-if="deleteLoading" class="size-4" />
              <span v-else>Delete Permanently</span>
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Empty Trash Dialog -->
    <DialogResponsive v-model:open="emptyTrashDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">Empty Trash?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            Are you sure you want to permanently delete all
            <strong>{{ filteredProjects.length }}</strong> trashed projects? This action cannot be
            undone.
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <button
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              @click="emptyTrashDialogOpen = false"
            >
              Cancel
            </button>
            <button
              @click="handleEmptyTrash"
              :disabled="emptyTrashLoading"
              class="bg-destructive hover:bg-destructive/90 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Spinner v-if="emptyTrashLoading" class="size-4" />
              <span v-else>Empty Trash</span>
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import ProjectsFilters from "@/components/project/ProjectsFilters.vue";
import ProjectsHeader from "@/components/project/ProjectsHeader.vue";
import ProjectsList from "@/components/project/ProjectsList.vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "role"],
  roles: ["admin", "master"],
  layout: "app",
});

defineOptions({
  name: "projects-trash",
});

usePageMeta("projectTrash");

const { user } = useSanctumAuth();
const { isAdminOrMaster } = usePermission();

// Filter state
const searchQuery = ref("");
const selectedStatuses = ref([]);

// Fetch trashed projects with lazy loading (non-blocking navigation)
const {
  data: projectsResponse,
  pending,
  error,
  refresh,
} = await useLazySanctumFetch("/api/projects/trash", {
  query: {
    client_only: "true",
    sort: "-deleted_at",
  },
  key: "projects-trash-list",
});

// Extract data array from response
const data = computed(() => projectsResponse.value?.data || []);

// Check if filters are active
const hasActiveFilters = computed(() => {
  return searchQuery.value !== "" || selectedStatuses.value.length > 0;
});

// Filtered projects
const filteredProjects = computed(() => {
  let filtered = [...data.value];

  // Apply search filter
  if (searchQuery.value) {
    const search = searchQuery.value.toLowerCase();
    filtered = filtered.filter((project) => {
      return (
        project.name?.toLowerCase().includes(search) ||
        project.username?.toLowerCase().includes(search)
      );
    });
  }

  // Apply status filter
  if (selectedStatuses.value.length > 0) {
    filtered = filtered.filter((project) => selectedStatuses.value.includes(project.status));
  }

  return filtered;
});

// Restore functionality
const restoreDialogOpen = ref(false);
const projectToRestore = ref(null);
const restoreLoading = ref(false);

const openRestoreDialog = (project) => {
  projectToRestore.value = project;
  restoreDialogOpen.value = true;
};

const handleRestoreProject = async () => {
  if (!projectToRestore.value) return;

  try {
    restoreLoading.value = true;
    const client = useSanctumClient();
    const response = await client(`/api/projects/trash/${projectToRestore.value.id}/restore`, {
      method: "POST",
    });

    toast.success(response.message || "Project restored successfully");
    restoreDialogOpen.value = false;
    projectToRestore.value = null;
    await refresh();
  } catch (error) {
    console.error("Failed to restore project:", error);
    toast.error("Failed to restore project", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    restoreLoading.value = false;
  }
};

// Delete functionality
const deleteDialogOpen = ref(false);
const projectToDelete = ref(null);
const deleteLoading = ref(false);

const openDeleteDialog = (project) => {
  projectToDelete.value = project;
  deleteDialogOpen.value = true;
};

const handleDeleteProject = async () => {
  if (!projectToDelete.value) return;

  try {
    deleteLoading.value = true;
    const client = useSanctumClient();
    const response = await client(`/api/projects/trash/${projectToDelete.value.id}`, {
      method: "DELETE",
    });

    toast.success(response.message || "Project permanently deleted");
    deleteDialogOpen.value = false;
    projectToDelete.value = null;
    await refresh();
  } catch (error) {
    console.error("Failed to permanently delete project:", error);
    toast.error("Failed to permanently delete project", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    deleteLoading.value = false;
  }
};

// Empty trash functionality
const emptyTrashDialogOpen = ref(false);
const emptyTrashLoading = ref(false);

const openEmptyTrashDialog = () => {
  emptyTrashDialogOpen.value = true;
};

const handleEmptyTrash = async () => {
  try {
    emptyTrashLoading.value = true;
    const client = useSanctumClient();
    const projectIds = filteredProjects.value.map((p) => p.id);

    const response = await client("/api/projects/trash/bulk", {
      method: "DELETE",
      body: { ids: projectIds },
    });

    toast.success(response.message || "Trash emptied successfully", {
      description:
        response.errors?.length > 0
          ? `${response.deleted_count} deleted, ${response.errors.length} failed`
          : `${response.deleted_count} project(s) permanently deleted`,
    });
    emptyTrashDialogOpen.value = false;
    await refresh();
  } catch (error) {
    console.error("Failed to empty trash:", error);
    toast.error("Failed to empty trash", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    emptyTrashLoading.value = false;
  }
};
</script>
