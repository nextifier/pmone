<template>
  <div class="mx-auto max-w-xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:layers-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Project Management</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <ImportDialog
          v-if="user?.roles?.some((role) => ['master', 'admin'].includes(role))"
          @imported="refresh"
        >
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
          v-if="user?.roles?.some((role) => ['master', 'admin'].includes(role))"
          @click="handleExport"
          :disabled="exportPending"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Spinner v-if="exportPending" class="size-4 shrink-0" />
          <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
          <span>Export</span>
        </button>

        <nuxt-link
          v-if="user?.roles?.some((role) => ['master', 'admin'].includes(role))"
          to="/projects/trash"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
          <span>Trash</span>
        </nuxt-link>
      </div>
    </div>

    <div class="grid gap-y-3">
      <div class="flex gap-x-2">
        <div class="relative grow">
          <Icon
            name="lucide:search"
            class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
          />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search projects"
            class="placeholder:text-muted-foreground h-9 w-full rounded-md border bg-transparent px-9 py-1.5 text-sm tracking-tight focus:outline-hidden"
          />
          <button
            v-if="searchQuery"
            @click="searchQuery = ''"
            class="bg-muted hover:bg-border absolute top-1/2 right-3 flex size-6 -translate-y-1/2 items-center justify-center rounded-full"
            aria-label="Clear search"
          >
            <Icon name="lucide:x" class="size-3 shrink-0" />
          </button>
        </div>

        <Popover>
          <PopoverTrigger asChild>
            <button
              class="hover:bg-muted relative flex h-9 shrink-0 items-center justify-center gap-x-1.5 rounded-md border px-3 text-sm tracking-tight active:scale-98"
            >
              <Icon name="lucide:list-filter" class="size-4 shrink-0" />
              <span>Filter</span>
              <span
                v-if="selectedStatuses.length > 0"
                class="bg-primary text-primary-foreground squircle absolute top-0 right-0 inline-flex size-4 translate-x-1/2 -translate-y-1/2 items-center justify-center text-[11px] font-medium tracking-tight"
              >
                {{ selectedStatuses.length }}
              </span>
            </button>
          </PopoverTrigger>
          <PopoverContent class="w-48 p-3" align="end">
            <div class="space-y-3">
              <div class="text-muted-foreground text-xs font-medium">Status</div>
              <div class="space-y-2">
                <div
                  v-for="status in ['active', 'draft', 'archived']"
                  :key="status"
                  class="flex items-center gap-2"
                >
                  <Checkbox
                    :id="`status-${status}`"
                    :model-value="selectedStatuses.includes(status)"
                    @update:model-value="(checked) => toggleStatus(status, checked)"
                  />
                  <Label
                    :for="`status-${status}`"
                    class="grow cursor-pointer font-normal tracking-tight capitalize"
                  >
                    {{ status }}
                  </Label>
                </div>
              </div>
            </div>
          </PopoverContent>
        </Popover>
      </div>

      <div class="flex h-9 justify-end gap-x-2">
        <button
          @click="refresh"
          :disabled="pending"
          class="hover:bg-muted flex aspect-square h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border text-sm tracking-tight active:scale-98 sm:aspect-auto sm:px-2.5"
        >
          <Icon
            name="lucide:refresh-cw"
            class="size-4 shrink-0"
            :class="pending ? 'animate-spin' : ''"
          />
          <span class="hidden sm:flex">Refresh</span>
        </button>

        <NuxtLink
          v-if="user?.roles?.some((role) => ['master', 'admin'].includes(role))"
          to="/projects/create"
          class="hover:bg-primary/80 text-primary-foreground bg-primary flex items-center gap-x-1.5 rounded-md border px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
        >
          <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
          <span>Add Project</span>
        </NuxtLink>
      </div>
    </div>

    <!-- Error State -->
    <div
      v-if="error"
      class="border-destructive/50 bg-destructive/10 flex flex-col items-start gap-y-3 rounded-lg border p-4"
    >
      <div class="text-destructive flex items-center gap-x-2">
        <Icon name="hugeicons:alert-circle" class="size-5" />
        <span class="font-medium tracking-tight">Error loading projects</span>
      </div>
      <p class="text-sm tracking-tight">
        {{ error?.message || "An error occurred while fetching data." }}
      </p>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="filteredProjects.length === 0"
      class="flex flex-col items-center gap-4 rounded-lg border border-dashed py-12 text-center"
    >
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
        <h3 class="text-lg font-semibold tracking-tighter">No projects found</h3>
        <p class="text-muted-foreground text-sm tracking-tight">
          {{
            searchQuery
              ? "Try adjusting your search query."
              : "Get started by creating a new project."
          }}
        </p>
      </div>
      <NuxtLink
        v-if="!searchQuery"
        to="/projects/create"
        class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
      >
        <Icon name="lucide:plus" class="size-4 shrink-0" />
        <span>Create Project</span>
      </NuxtLink>
    </div>

    <!-- Projects List (Sortable) -->
    <div v-else ref="projectsList" class="divide-border divide-y rounded-xl border">
      <div
        v-for="project in filteredProjects"
        :key="project.id"
        :data-id="project.id"
        class="hover:bg-muted dark:hover:bg-muted bg-background dark:bg-muted/50 relative isolate flex items-center gap-x-2 px-3 py-4 first:rounded-t-xl last:rounded-b-xl"
      >
        <NuxtLink :to="`/projects/${project.username}/edit`" class="absolute inset-0 z-10" />

        <!-- Drag Handle -->
        <div
          class="hover:bg-muted text-muted-foreground hover:text-primary relative z-20 -mx-1 flex size-8 shrink-0 items-center justify-center rounded-md transition-colors"
          :class="
            hasActiveFilters
              ? 'cursor-not-allowed opacity-30'
              : 'drag-handle cursor-grab active:cursor-grabbing'
          "
        >
          <Icon name="lucide:grip-vertical" class="size-5" />
        </div>

        <div class="flex w-full items-center gap-x-2">
          <Avatar :model="project" class="squircle size-12 overflow-hidden border" />

          <div class="flex grow flex-col gap-y-1.5">
            <div class="flex items-center gap-x-2">
              <h3 class="line-clamp-1 text-sm font-semibold tracking-tight">{{ project.name }}</h3>
            </div>

            <div class="text-muted-foreground flex items-center gap-x-3 text-xs tracking-tight">
              <span v-if="project.members?.length" class="flex items-center gap-x-1">
                <div class="flex -space-x-1.5">
                  <Avatar
                    v-for="member in project.members.slice(0, 4)"
                    :model="member"
                    :key="member.id"
                    class="bg-muted ring-background [&_.initial]:text-muted-foreground size-6 shrink-0 overflow-hidden rounded-full ring-1 [&_.initial]:text-[10px] [&_.initial]:font-medium"
                    v-tippy="member.name"
                  />
                  <span
                    v-if="project.members_count && project.members_count > 4"
                    class="ring-background bg-muted text-muted-foreground relative flex size-6 shrink-0 items-center justify-center overflow-hidden rounded-full border text-center text-[10px] font-medium tracking-tighter ring-1"
                    >+{{ project.members_count - 4 }}</span
                  >
                </div>
                <span v-if="project.members_count"
                  >{{ project.members_count }} member{{
                    project.members_count > 1 ? "s" : ""
                  }}</span
                >
              </span>
            </div>
          </div>
        </div>

        <div class="mt-2 flex shrink-0 flex-col items-end gap-y-1.5">
          <span
            class="flex items-center gap-x-1 rounded-full px-2 py-0.5 text-xs font-medium tracking-tight capitalize"
            :class="{
              'bg-success/10 text-success-foreground': project.status === 'active',
              'bg-warning/10 text-warning-foreground': project.status === 'draft',
              'bg-muted text-muted-foreground': project.status === 'archived',
            }"
          >
            <span
              class="size-1.5 rounded-full"
              :class="{
                'bg-success': project.status === 'active',
                'bg-warning': project.status === 'draft',
                'bg-muted-foreground': project.status === 'archived',
              }"
            ></span>
            {{ project.status }}
          </span>

          <Popover>
            <PopoverTrigger asChild>
              <button
                class="hover:bg-border data-[state=open]:bg-muted text-muted-foreground hover:text-foreground data-[state=open]:text-foreground relative z-20 inline-flex size-8 items-center justify-center rounded-md"
              >
                <Icon name="lucide:ellipsis" class="size-4" />
              </button>
            </PopoverTrigger>
            <PopoverContent align="end" class="w-40 p-1">
              <NuxtLink
                :to="`/projects/${project.username}/edit`"
                class="hover:bg-muted hover:text-foreground flex w-full items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
              >
                <Icon name="lucide:pencil-line" class="size-4 shrink-0" />
                <span>Edit</span>
              </NuxtLink>

              <button
                @click="openDeleteDialog(project)"
                class="hover:bg-destructive/10 text-destructive flex w-full items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
              >
                <Icon name="lucide:trash" class="size-4 shrink-0" />
                <span>Delete</span>
              </button>
            </PopoverContent>
          </Popover>
        </div>
      </div>
    </div>

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
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { useSortable } from "@vueuse/integrations/useSortable";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "staff-admin-master"],
  layout: "app",
});

defineOptions({
  name: "projects",
});

usePageMeta("projects");

const { user } = useSanctumAuth();

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
        project.username?.toLowerCase().includes(search) ||
        project.email?.toLowerCase().includes(search)
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

// Toggle status filter
const toggleStatus = (status, checked) => {
  if (checked) {
    if (!selectedStatuses.value.includes(status)) {
      selectedStatuses.value.push(status);
    }
  } else {
    const index = selectedStatuses.value.indexOf(status);
    if (index > -1) {
      selectedStatuses.value.splice(index, 1);
    }
  }
};

// Sortable functionality
const projectsList = ref(null);
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
  if (!hasActiveFilters.value && projectsList.value) {
    nextTick(() => {
      sortableInstance = useSortable(projectsList.value, data, {
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

    const params = new URLSearchParams();
    params.append("sort", "order_column");

    if (selectedStatuses.value.length > 0) {
      params.append("filter.status", selectedStatuses.value.join(","));
    }

    const client = useSanctumClient();

    const response = await client(`/api/projects/export?${params.toString()}`, {
      responseType: "blob",
    });

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
