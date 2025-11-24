<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <ProjectsHeader title="Project Management">
      <template #actions>
        <ImportDialog v-if="isAdminOrMaster" @imported="refresh">
          <template #trigger="{ open }">
            <button
              @click="open()"
              class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
            >
              <Icon name="hugeicons:file-import" class="size-4 shrink-0" />
              <span>Import</span>
            </button>
          </template>
        </ImportDialog>

        <button
          v-if="isAdminOrMaster"
          @click="handleExport"
          :disabled="exportPending"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Spinner v-if="exportPending" class="size-4 shrink-0" />
          <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
          <span>Export {{ hasActiveFilters ? "selected" : "all" }}</span>
        </button>

        <nuxt-link
          v-if="isAdminOrMaster"
          to="/projects/trash"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
          <span>Trash</span>
        </nuxt-link>
      </template>
    </ProjectsHeader>

    <ProjectsFilters
      v-model:search-query="searchQuery"
      v-model:selected-statuses="selectedStatuses"
      :pending="pending"
      @refresh="refresh"
    >
      <template #actions>
        <NuxtLink
          v-if="isAdminOrMaster"
          to="/projects/create"
          class="hover:bg-primary/80 text-primary-foreground bg-primary flex items-center gap-x-1.5 rounded-md border px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
        >
          <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
          <span>Add project</span>
        </NuxtLink>
      </template>
    </ProjectsFilters>

    <ProjectsList
      ref="projectsListRef"
      :projects="filteredProjects"
      :error="error"
      :has-active-filters="hasActiveFilters"
      :empty-description="
        searchQuery ? 'Try adjusting your search query.' : 'Get started by creating a new project.'
      "
    >
      <template #empty-actions>
        <NuxtLink
          v-if="!searchQuery"
          to="/projects/create"
          class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
        >
          <Icon name="lucide:plus" class="size-4 shrink-0" />
          <span>Create Project</span>
        </NuxtLink>
      </template>

      <template #row-actions="{ project }">
        <NuxtLink
          :to="`/projects/${project.username}`"
          class="hover:bg-muted hover:text-foreground flex w-full items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
        >
          <Icon name="lucide:user-round-search" class="size-4 shrink-0" />
          <span>Profile</span>
        </NuxtLink>

        <NuxtLink
          :to="`/projects/${project.username}/edit`"
          class="hover:bg-muted hover:text-foreground flex w-full items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
        >
          <Icon name="lucide:pencil-line" class="size-4 shrink-0" />
          <span>Edit</span>
        </NuxtLink>

        <NuxtLink
          :to="`/projects/${project.username}/analytics`"
          class="hover:bg-muted hover:text-foreground flex w-full items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
        >
          <Icon name="lucide:chart-no-axes-combined" class="size-4 shrink-0" />
          <span>Analytics</span>
        </NuxtLink>

        <button
          @click="openDeleteDialog(project)"
          class="hover:bg-destructive/10 text-destructive flex w-full items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
        >
          <Icon name="lucide:trash" class="size-4 shrink-0" />
          <span>Delete</span>
        </button>
      </template>
    </ProjectsList>

    <!-- Delete Dialog -->
    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">Delete Project?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            Are you sure you want to delete <strong>{{ projectToDelete?.name }}</strong
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
              <span v-else>Delete</span>
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import ImportDialog from "@/components/project/ImportDialog.vue";
import ProjectsFilters from "@/components/project/ProjectsFilters.vue";
import ProjectsHeader from "@/components/project/ProjectsHeader.vue";
import ProjectsList from "@/components/project/ProjectsList.vue";
import { useSortable } from "@vueuse/integrations/useSortable";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "role"],
  roles: ["staff", "admin", "master"],
  layout: "app",
});

defineOptions({
  name: "projects",
});

usePageMeta("projects");

const { user } = useSanctumAuth();
const { isAdminOrMaster } = usePermission();

// Data state
const data = ref([]);
const pending = ref(false);
const error = ref(null);
const searchQuery = ref("");
const selectedStatuses = ref([]);

// Fetch projects
const fetchProjects = async () => {
  try {
    pending.value = true;
    error.value = null;
    const client = useSanctumClient();
    const params = new URLSearchParams();
    params.append("client_only", "true");
    params.append("sort", "order_column");
    const response = await client(`/api/projects?${params.toString()}`);
    data.value = response.data;
  } catch (err) {
    error.value = err;
    console.error("Failed to fetch projects:", err);
  } finally {
    pending.value = false;
  }
};

await fetchProjects();

const refresh = fetchProjects;

// Check if filters are active
const hasActiveFilters = computed(() => {
  return searchQuery.value !== "" || selectedStatuses.value.length > 0;
});

// Filtered projects
const filteredProjects = computed(() => {
  // If no filters, return data directly to allow drag & drop
  if (!hasActiveFilters.value) {
    return data.value;
  }

  // Otherwise apply filters
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

  // Sort filtered results
  filtered.sort((a, b) => {
    const orderA = Number(a.order_column) || 0;
    const orderB = Number(b.order_column) || 0;
    return orderA - orderB;
  });

  return filtered;
});

// Sortable functionality
const projectsListRef = ref(null);
const isSyncing = ref(false);

const updateProjectOrder = async () => {
  if (isSyncing.value) return;

  try {
    isSyncing.value = true;
    const client = useSanctumClient();

    const orders = data.value.map((project, index) => ({
      id: project.id,
      order: index + 1,
    }));

    await client("/api/projects/update-order", {
      method: "POST",
      body: { orders },
    });

    // Update local order_column to match new order
    data.value.forEach((project, index) => {
      project.order_column = index + 1;
    });

    toast.success("Project order updated successfully");
  } catch (error) {
    console.error("Failed to update project order:", error);
    toast.error("Failed to update project order", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
    await refresh();
  } finally {
    isSyncing.value = false;
  }
};

// Initialize sortable
let sortableInstance = null;

const initializeSortable = () => {
  // Destroy existing instance if any
  if (sortableInstance?.stop) {
    sortableInstance.stop();
    sortableInstance = null;
  }

  // Only create sortable when no filters are active
  if (!hasActiveFilters.value && projectsListRef.value?.projectsListEl) {
    nextTick(() => {
      sortableInstance = useSortable(projectsListRef.value.projectsListEl, data, {
        animation: 200,
        handle: ".drag-handle",
        ghostClass: "sortable-ghost",
        chosenClass: "sortable-chosen",
        dragClass: "sortable-drag",
        onEnd: async () => {
          await nextTick();
          await updateProjectOrder();
        },
      });
    });
  }
};

onMounted(() => {
  initializeSortable();
});

// Watch for filter changes to reinitialize sortable
watch(hasActiveFilters, () => {
  initializeSortable();
});

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
    const response = await client(`/api/projects/${projectToDelete.value.username}`, {
      method: "DELETE",
    });

    toast.success(response.message || "Project deleted successfully");
    deleteDialogOpen.value = false;
    projectToDelete.value = null;
    await refresh();
  } catch (error) {
    console.error("Failed to delete project:", error);
    toast.error("Failed to delete project", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    deleteLoading.value = false;
  }
};

// Export handler
const exportPending = ref(false);
const handleExport = async () => {
  try {
    exportPending.value = true;

    // Build query params
    const params = new URLSearchParams();

    // Add search filter if present
    if (searchQuery.value) {
      params.append("filter.search", searchQuery.value);
    }

    // Add status filter if present
    if (selectedStatuses.value.length > 0) {
      params.append("filter.status", selectedStatuses.value.join(","));
    }

    // Add sorting (default to order_column)
    params.append("sort", "order_column");

    const client = useSanctumClient();

    // Fetch the file as blob
    const response = await client(`/api/projects/export?${params.toString()}`, {
      responseType: "blob",
    });

    // Create a download link and trigger download
    const blob = new Blob([response], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `projects_${new Date().toISOString().slice(0, 19).replace(/:/g, "-")}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    toast.success("Projects exported successfully");
  } catch (error) {
    console.error("Failed to export projects:", error);
    toast.error("Failed to export projects", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    exportPending.value = false;
  }
};
</script>
