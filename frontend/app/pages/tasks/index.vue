<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <!-- Header -->
    <TasksHeader title="Task Management" icon="hugeicons:task-01">
      <template #actions>
        <NuxtLink
          to="/tasks/trash"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
          <span>Trash</span>
        </NuxtLink>
      </template>
    </TasksHeader>

    <!-- Filters -->
    <TasksFilters
      v-model:search-query="searchQuery"
      v-model:selected-statuses="selectedStatuses"
      v-model:selected-priorities="selectedPriorities"
      :pending="pending"
      @refresh="refresh"
    >
      <template #actions>
        <NuxtLink
          to="/tasks/create"
          class="hover:bg-primary/80 text-primary-foreground bg-primary flex items-center gap-x-1.5 rounded-md border px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
        >
          <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
          <span>Add task</span>
        </NuxtLink>
      </template>
    </TasksFilters>

    <!-- Loading State -->
    <div v-if="pending" class="flex justify-center py-12">
      <Spinner class="size-8" />
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="border-border bg-card rounded-lg border p-12 text-center">
      <Icon name="lucide:alert-circle" class="text-destructive mx-auto mb-3 size-12" />
      <p class="text-muted-foreground text-sm">Failed to load tasks. Please try again.</p>
      <Button variant="outline" size="sm" class="mt-4" @click="refresh">
        <Icon name="lucide:refresh-cw" class="size-4" />
        <span>Try Again</span>
      </Button>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="filteredTasks.length === 0"
      class="flex flex-col items-center justify-center pt-10 text-center"
    >
      <Icon name="hugeicons:task-01" class="text-muted-foreground mx-auto mb-3 size-12" />
      <span class="text-base font-semibold tracking-tight">No tasks found.</span>
      <span class="text-muted-foreground mt-1 text-sm">
        {{
          searchQuery
            ? "Try adjusting your search query."
            : "Create your first task to get started!"
        }}
      </span>
      <NuxtLink
        v-if="!searchQuery"
        to="/tasks/create"
        class="bg-primary text-primary-foreground hover:bg-primary/80 mt-4 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
      >
        <Icon name="lucide:plus" class="size-4 shrink-0" />
        <span>Create Task</span>
      </NuxtLink>
    </div>

    <!-- Projects with Tasks (Full Width) -->
    <div v-else class="space-y-6">
      <div
        v-for="projectGroup in groupedTasksByProject"
        :key="projectGroup.project?.id || 'no-project'"
        class="border-border bg-card rounded-xl border"
      >
        <!-- Project Header -->
        <div class="border-border flex items-center gap-3 border-b p-4">
          <!-- Project Avatar -->
          <NuxtLink
            v-if="projectGroup.project"
            :to="`/tasks/${projectGroup.project.username}`"
            class="bg-muted border-border relative size-12 shrink-0 overflow-hidden rounded-2xl border"
          >
            <NuxtImg
              v-if="projectGroup.project.profile_image?.sm"
              :src="projectGroup.project.profile_image.sm"
              :alt="projectGroup.project.name"
              class="size-full object-contain"
            />
            <div v-else class="flex size-full items-center justify-center">
              <Icon name="lucide:folder" class="text-muted-foreground size-5" />
            </div>
          </NuxtLink>
          <div
            v-else
            class="bg-muted border-border flex size-12 shrink-0 items-center justify-center rounded-2xl border"
          >
            <Icon name="lucide:inbox" class="text-muted-foreground size-5" />
          </div>

          <!-- Project Info -->
          <div class="flex flex-1 flex-col gap-y-1">
            <NuxtLink
              v-if="projectGroup.project"
              :to="`/tasks/${projectGroup.project.username}`"
              class="text-primary text-sm font-semibold hover:underline"
            >
              {{ projectGroup.project.name }}
            </NuxtLink>
            <span v-else class="text-muted-foreground text-sm font-semibold"> No Project </span>

            <!-- Social Links -->
            <div v-if="projectGroup.project" class="flex items-center gap-x-3">
              <NuxtLink
                v-if="projectGroup.project.more_details?.instagram"
                :to="`https://www.instagram.com/${projectGroup.project.more_details.instagram}`"
                target="_blank"
                class="text-muted-foreground hover:text-primary transition"
              >
                <Icon name="hugeicons:instagram" class="size-4" />
              </NuxtLink>
              <NuxtLink
                v-if="projectGroup.project.more_details?.website"
                :to="projectGroup.project.more_details.website"
                target="_blank"
                class="text-muted-foreground hover:text-primary transition"
              >
                <Icon name="hugeicons:globe-02" class="size-4" />
              </NuxtLink>
            </div>
          </div>

          <!-- Task Count & View All -->
          <div class="flex items-center gap-3">
            <Badge variant="secondary"> {{ projectGroup.allTasks.length }} tasks </Badge>
            <NuxtLink
              v-if="projectGroup.project"
              :to="`/tasks/${projectGroup.project.username}`"
              class="text-primary/80 hover:text-primary flex items-center gap-x-1 text-sm font-medium tracking-tight transition hover:underline"
            >
              <span>View all</span>
              <Icon name="lucide:arrow-right" class="size-4 shrink-0" />
            </NuxtLink>
          </div>
        </div>

        <!-- Tasks by Status (2 columns: To Do & Completed) -->
        <div class="divide-border grid grid-cols-2 divide-x">
          <!-- To Do Column (includes In Progress at top) -->
          <div class="p-4">
            <div class="mb-3 flex items-center gap-2">
              <Icon name="lucide:circle-dashed" class="text-muted-foreground size-4" />
              <span class="text-xs font-semibold tracking-tight">To Do</span>
              <Badge variant="outline" class="text-xs">
                {{ projectGroup.pendingTasks.length }}
              </Badge>
            </div>
            <div v-if="projectGroup.pendingTasks.length > 0" class="space-y-2">
              <TaskCard
                v-for="task in projectGroup.pendingTasks"
                :key="task.id"
                :task="task"
                @update-status="handleUpdateStatus"
                @delete="openDeleteDialog"
              />
            </div>
            <div v-else class="flex flex-col items-center justify-center py-8 text-center">
              <Icon name="lucide:inbox" class="text-muted-foreground/50 mb-2 size-8" />
              <span class="text-muted-foreground text-xs">No pending tasks</span>
            </div>
          </div>

          <!-- Completed Column -->
          <div class="p-4">
            <div class="mb-3 flex items-center gap-2">
              <Icon name="lucide:check" class="size-4 text-green-600 dark:text-green-500" />
              <span class="text-xs font-semibold tracking-tight">Completed</span>
              <Badge variant="outline" class="text-xs">
                {{
                  projectGroup.completedTasks.length > 5
                    ? `5 of ${projectGroup.completedTasks.length}`
                    : projectGroup.completedTasks.length
                }}
              </Badge>
            </div>
            <div v-if="projectGroup.completedTasks.length > 0" class="space-y-2">
              <TaskCard
                v-for="task in projectGroup.completedTasks.slice(0, 5)"
                :key="task.id"
                :task="task"
                @update-status="handleUpdateStatus"
                @delete="openDeleteDialog"
              />
              <NuxtLink
                v-if="projectGroup.project && projectGroup.completedTasks.length > 5"
                :to="`/tasks/${projectGroup.project.username}`"
                class="text-muted-foreground hover:text-primary mt-3 flex items-center gap-x-1 text-xs font-medium tracking-tight transition"
              >
                <span>View {{ projectGroup.completedTasks.length - 5 }} more completed tasks</span>
                <Icon name="lucide:arrow-right" class="size-3 shrink-0" />
              </NuxtLink>
            </div>
            <div v-else class="flex flex-col items-center justify-center py-8 text-center">
              <Icon name="lucide:circle-check" class="text-muted-foreground/50 mb-2 size-8" />
              <span class="text-muted-foreground text-xs">No completed tasks</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Dialog -->
    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-foreground text-lg font-semibold tracking-tight">Delete Task?</div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            Are you sure you want to delete <strong>{{ taskToDelete?.title }}</strong
            >? This action can be undone from trash.
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <Button variant="outline" @click="deleteDialogOpen = false"> Cancel </Button>
            <Button variant="destructive" @click="handleDeleteTask" :disabled="deleteLoading">
              <Spinner v-if="deleteLoading" class="size-4" />
              <span v-else>Delete</span>
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import TaskCard from "@/components/task/TaskCard.vue";
import TasksFilters from "@/components/task/TasksFilters.vue";
import TasksHeader from "@/components/task/TasksHeader.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

// Filter state
const searchQuery = ref("");
const selectedStatuses = ref([]);
const selectedPriorities = ref([]);

// Fetch tasks
const {
  data: tasksResponse,
  pending,
  error,
  refresh: fetchRefresh,
} = await useLazySanctumFetch("/api/tasks?per_page=100", {
  key: "tasks-list",
});

// Local reactive copy of tasks for optimistic updates
const localTasks = ref([]);

// Sync API data to local state
watch(
  () => tasksResponse.value?.data,
  (newData) => {
    if (newData) {
      localTasks.value = JSON.parse(JSON.stringify(newData));
    }
  },
  { immediate: true }
);

// Refresh function that updates local state
const refresh = async () => {
  await fetchRefresh();
};

const allTasks = computed(() => localTasks.value);

// Filter tasks
const filteredTasks = computed(() => {
  let filtered = allTasks.value;

  // Search filter
  if (searchQuery.value) {
    const search = searchQuery.value.toLowerCase();
    filtered = filtered.filter(
      (task) =>
        task.title.toLowerCase().includes(search) ||
        task.description?.toLowerCase().includes(search) ||
        task.project?.name?.toLowerCase().includes(search)
    );
  }

  // Status filter
  if (selectedStatuses.value.length > 0) {
    filtered = filtered.filter((task) => selectedStatuses.value.includes(task.status));
  }

  // Priority filter
  if (selectedPriorities.value.length > 0) {
    filtered = filtered.filter((task) => selectedPriorities.value.includes(task.priority));
  }

  return filtered;
});

// Group tasks by project
const groupedTasksByProject = computed(() => {
  const grouped = {};

  filteredTasks.value.forEach((task) => {
    const projectId = task.project?.id || "no-project";

    if (!grouped[projectId]) {
      grouped[projectId] = {
        project: task.project,
        allTasks: [],
        inProgressTasks: [],
        todoTasks: [],
        completedTasks: [],
      };
    }

    grouped[projectId].allTasks.push(task);

    if (task.status === "in_progress") {
      grouped[projectId].inProgressTasks.push(task);
    } else if (task.status === "todo") {
      grouped[projectId].todoTasks.push(task);
    } else if (task.status === "completed") {
      grouped[projectId].completedTasks.push(task);
    }
  });

  // Sort and combine tasks
  Object.values(grouped).forEach((group) => {
    // Sort completed tasks by completed_at (newest first)
    group.completedTasks.sort((a, b) => {
      const dateA = a.completed_at ? new Date(a.completed_at) : new Date(0);
      const dateB = b.completed_at ? new Date(b.completed_at) : new Date(0);
      return dateB - dateA;
    });

    // Combine in_progress + todo tasks (in_progress first)
    group.pendingTasks = [...group.inProgressTasks, ...group.todoTasks];
  });

  // Convert to array and sort alphabetically, with "No Project" at the end
  return Object.values(grouped).sort((a, b) => {
    // "No Project" always last
    if (!a.project) return 1;
    if (!b.project) return -1;
    // Sort alphabetically by project name
    return a.project.name.localeCompare(b.project.name);
  });
});

// Update task status with optimistic update
const handleUpdateStatus = async (task, newStatus) => {
  // Find task index in local state
  const taskIndex = localTasks.value.findIndex((t) => t.id === task.id);
  if (taskIndex === -1) return;

  const oldStatus = localTasks.value[taskIndex].status;
  const oldCompletedAt = localTasks.value[taskIndex].completed_at;

  // Optimistic update - update local data immediately
  localTasks.value[taskIndex] = {
    ...localTasks.value[taskIndex],
    status: newStatus,
    completed_at: newStatus === "completed" ? new Date().toISOString() : null,
  };

  const statusLabels = {
    completed: "completed",
    todo: "moved to To Do",
    in_progress: "started",
  };

  try {
    const client = useSanctumClient();
    await client(`/api/tasks/${task.ulid}`, {
      method: "PUT",
      body: { status: newStatus },
    });

    toast.success(`Task ${statusLabels[newStatus] || newStatus}`);
  } catch (err) {
    // Rollback on error
    localTasks.value[taskIndex] = {
      ...localTasks.value[taskIndex],
      status: oldStatus,
      completed_at: oldCompletedAt,
    };
    console.error("Failed to update task status:", err);
    toast.error("Failed to update task status");
  }
};

// Delete functionality
const deleteDialogOpen = ref(false);
const taskToDelete = ref(null);
const deleteLoading = ref(false);

const openDeleteDialog = (task) => {
  taskToDelete.value = task;
  deleteDialogOpen.value = true;
};

const handleDeleteTask = async () => {
  if (!taskToDelete.value) return;

  deleteLoading.value = true;
  try {
    const client = useSanctumClient();
    await client(`/api/tasks/${taskToDelete.value.ulid}`, {
      method: "DELETE",
    });

    await refresh();
    deleteDialogOpen.value = false;
    taskToDelete.value = null;
    toast.success("Task deleted successfully");
  } catch (err) {
    console.error("Failed to delete task:", err);
    toast.error("Failed to delete task");
  } finally {
    deleteLoading.value = false;
  }
};

// Set page meta
useHead({
  title: "Tasks",
});
</script>
