<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <!-- Header -->
    <TasksHeader title="My Tasks" icon="hugeicons:task-daily-01">
      <template #actions>
        <NuxtLink
          to="/tasks/all"
          class="border-border hover:bg-muted flex items-center gap-x-1.5 rounded-md border px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:user-group" class="size-4 shrink-0" />
          <span>All Tasks</span>
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
        <button
          v-if="canCreate"
          type="button"
          @click="dialogs.openCreateDialog"
          class="hover:bg-primary/80 text-primary-foreground bg-primary flex items-center gap-x-1.5 rounded-md border px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:plus-sign" class="-ml-1 size-4 shrink-0" />
          <span>Add task</span>
        </button>
      </template>
    </TasksFilters>

    <!-- Loading State -->
    <LoadingState
      v-if="pending && !tasksResponse?.data"
      label="Loading data.."
      class="my-6 border-0"
    />

    <!-- Error State -->
    <div v-else-if="error" class="border-border bg-card rounded-lg border p-12 text-center">
      <Icon name="hugeicons:alert-02" class="text-destructive mx-auto mb-3 size-12" />
      <p class="text-muted-foreground text-sm">Failed to load tasks. Please try again.</p>
      <Button variant="outline" size="sm" class="mt-4" @click="refresh">
        <Icon name="hugeicons:refresh" class="size-4" />
        <span>Try Again</span>
      </Button>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="filteredTasks.length === 0"
      class="flex flex-col items-center justify-center pt-10 text-center"
    >
      <Icon name="hugeicons:task-daily-01" class="text-muted-foreground mx-auto mb-3 size-12" />
      <span class="text-base font-semibold tracking-tight">No tasks found.</span>
      <span class="text-muted-foreground mt-1 text-sm">
        {{
          searchQuery
            ? "Try adjusting your search query."
            : "Create your first task to get started!"
        }}
      </span>
      <button
        v-if="!searchQuery && canCreate"
        type="button"
        @click="dialogs.openCreateDialog"
        class="bg-primary text-primary-foreground hover:bg-primary/80 mt-4 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
      >
        <Icon name="hugeicons:plus-sign" class="size-4 shrink-0" />
        <span>Create Task</span>
      </button>
    </div>

    <!-- Tasks Content -->
    <template v-else>
      <!-- 2-Column Layout: Active (left) | Completed (right) -->
      <div class="grid grid-cols-1 items-start gap-x-3 gap-y-5 lg:grid-cols-2">
        <!-- Left Column: In Progress + To Do -->
        <div class="border-border bg-card rounded-xl border">
          <div class="flex flex-col divide-y">
            <!-- In Progress Section -->
            <div v-if="inProgressTasks.length > 0" class="flex flex-col gap-y-4 px-3 py-5">
              <div class="flex items-center gap-x-2">
                <Icon name="hugeicons:loading-03" class="text-info-foreground size-4.5" />
                <span class="text-sm font-medium tracking-tight">In Progress</span>
                <Badge variant="secondary" class="h-4 px-1.5 text-[10px]">
                  {{ inProgressTasks.length }}
                </Badge>
              </div>
              <div ref="inProgressListEl" class="space-y-4">
                <TaskCard
                  v-for="task in inProgressTasks"
                  :key="task.id"
                  :task="task"
                  :show-details="showDetails"
                  :can-edit="task.can_edit !== false"
                  @update-status="handleUpdateStatus"
                  @update-title="handleUpdateTitle"
                  @delete="dialogs.openDeleteDialog"
                  @view="dialogs.openDetailDialog"
                  @edit="dialogs.openEditDialog"
                />
              </div>
            </div>

            <!-- To Do Section -->
            <div v-if="todoTasks.length > 0" class="flex flex-col gap-y-4 px-3 py-5">
              <div class="flex items-center gap-x-2">
                <Icon name="hugeicons:task-daily-01" class="text-muted-foreground size-4.5" />
                <span class="text-sm font-medium tracking-tight">To Do</span>
                <Badge variant="secondary" class="h-4 px-1.5 text-[10px]">
                  {{ todoTasks.length }}
                </Badge>
              </div>
              <div ref="todoListEl" class="space-y-4">
                <TaskCard
                  v-for="task in todoTasks"
                  :key="task.id"
                  :task="task"
                  :show-details="showDetails"
                  :can-edit="task.can_edit !== false"
                  @update-status="handleUpdateStatus"
                  @update-title="handleUpdateTitle"
                  @delete="dialogs.openDeleteDialog"
                  @view="dialogs.openDetailDialog"
                  @edit="dialogs.openEditDialog"
                />
              </div>

              <!-- Quick Add -->
              <div v-if="canCreate" class="flex items-start gap-x-3.25 pl-6.25">
                <Icon
                  name="hugeicons:plus-sign"
                  class="text-muted-foreground mt-1 size-4.5 shrink-0"
                />
                <textarea
                  ref="quickAddInputEl"
                  v-model="quickAddTitle"
                  rows="1"
                  placeholder="Add task..."
                  class="text-foreground placeholder:text-muted-foreground/60 mt-px field-sizing-content min-h-0 w-full resize-none rounded-xs bg-transparent text-base tracking-tight outline-none"
                  @keydown.enter.prevent="handleQuickAdd"
                  @blur="handleQuickAdd"
                  :disabled="quickAddLoading"
                />
                <Spinner v-if="quickAddLoading" class="mt-1.5 size-4 shrink-0" />
              </div>
            </div>

            <!-- Empty pending -->
            <div
              v-if="pendingTasks.length === 0 && !quickAddTitle"
              class="flex flex-col items-center justify-center py-8 text-center"
            >
              <Icon name="hugeicons:task-daily-01" class="text-muted-foreground/50 mb-2 size-8" />
              <span class="text-muted-foreground text-sm tracking-tight">No active tasks</span>
            </div>
          </div>
        </div>

        <!-- Right Column: Completed -->
        <div class="border-border bg-card rounded-xl border">
          <div class="flex flex-col divide-y">
            <div class="flex flex-col gap-y-4 px-3 py-5">
              <div class="flex items-center gap-x-2">
                <Icon
                  name="hugeicons:checkmark-circle-02"
                  class="text-success-foreground size-4.5"
                />
                <span class="text-sm font-medium tracking-tight">Completed</span>
                <Badge variant="secondary" class="h-4 px-1.5 text-[10px]">
                  {{ completedTasks.length }}
                </Badge>
                <button
                  v-if="deletableCompletedTasks.length > 0"
                  type="button"
                  @click="openClearCompletedDialog"
                  class="hover:bg-muted ml-auto rounded-full px-2 py-1 text-sm tracking-tight"
                >
                  Clear all
                </button>
              </div>
              <div v-if="completedTasks.length > 0" ref="completedListEl" class="space-y-4">
                <TaskCard
                  v-for="task in completedTasks"
                  :key="task.id"
                  :task="task"
                  :show-details="showDetails"
                  :can-edit="task.can_edit !== false"
                  @update-status="handleUpdateStatus"
                  @update-title="handleUpdateTitle"
                  @delete="dialogs.openDeleteDialog"
                  @view="dialogs.openDetailDialog"
                  @edit="dialogs.openEditDialog"
                />
              </div>
              <div v-else class="flex flex-col items-center justify-center py-8 text-center">
                <Icon
                  name="hugeicons:checkmark-circle-02"
                  class="text-muted-foreground/50 mb-2 size-8"
                />
                <span class="text-muted-foreground text-sm tracking-tight">No completed tasks</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Task Dialogs -->
    <TaskDialogs
      :dialogs="dialogs"
      :with-create="true"
      :delete-mode="deleteMode"
      :deletable-completed-count="deletableCompletedTasks.length"
      @clear-completed="handleClearCompleted"
    />

    <!-- Floating Add Task Button -->
    <div
      v-if="canCreate"
      class="xs:right-[calc(var(--spacing)*4+var(--scrollbar-width,0px))] fixed right-[calc(var(--spacing)*3+var(--scrollbar-width,0px))] bottom-8 z-50 sm:right-[calc(var(--spacing)*6+var(--scrollbar-width,0px))] sm:bottom-5 lg:bottom-12 xl:right-[calc(var(--spacing)*12+var(--scrollbar-width,0px))]"
    >
      <GlassButton variant="default" size="icon-xl" @click="dialogs.openCreateDialog">
        <Icon name="hugeicons:plus-sign" class="size-5 shrink-0" />
      </GlassButton>
    </div>
  </div>
</template>

<script setup>
import TaskCard from "@/components/task/TaskCard.vue";
import TaskDialogs from "@/components/task/TaskDialogs.vue";
import TasksFilters from "@/components/task/TasksFilters.vue";
import TasksHeader from "@/components/task/TasksHeader.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { GlassButton } from "@/components/ui/glass-button";
import { useSortable } from "@vueuse/integrations/useSortable";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["tasks.read"],
  layout: "app",
});

const client = useSanctumClient();
const { user: currentUser } = useSanctumAuth();
const { hasPermission } = usePermission();

const canCreate = computed(() => hasPermission("tasks.create"));

// Show details toggle (persisted in localStorage)
const showDetails = ref(true);

onMounted(() => {
  const stored = localStorage.getItem("tasks-show-details");
  if (stored !== null) {
    showDetails.value = stored === "true";
  }
});

const toggleShowDetails = (checked) => {
  showDetails.value = checked;
  localStorage.setItem("tasks-show-details", String(checked));
};

// Filter state
const searchQuery = ref("");
const selectedStatuses = ref([]);
const selectedPriorities = ref([]);

// Fetch tasks sorted by order_column
const {
  data: tasksResponse,
  pending,
  error,
  refresh: fetchRefresh,
} = await useLazySanctumFetch("/api/tasks?per_page=100&sort_by=order_column&sort_order=asc", {
  key: "tasks-list",
});

// Writable refs for sortable (source of truth per status)
const inProgressTasksList = ref([]);
const todoTasksList = ref([]);
const completedTasksList = ref([]);

// Populate status lists from API data
const populateLists = (tasks) => {
  inProgressTasksList.value = tasks
    .filter((t) => t.status === "in_progress")
    .sort((a, b) => (a.order_column || 0) - (b.order_column || 0));
  todoTasksList.value = tasks
    .filter((t) => t.status === "todo")
    .sort((a, b) => (a.order_column || 0) - (b.order_column || 0));
  completedTasksList.value = tasks
    .filter((t) => t.status === "completed")
    .sort((a, b) => {
      const dateA = a.completed_at ? new Date(a.completed_at) : new Date(0);
      const dateB = b.completed_at ? new Date(b.completed_at) : new Date(0);
      return dateB - dateA;
    });
};

// Sync API data to writable refs
watch(
  () => tasksResponse.value?.data,
  (newData) => {
    if (newData) {
      populateLists(JSON.parse(JSON.stringify(newData)));
      nextTick(() => initializeSortable());
    }
  },
  { immediate: true }
);

// Refresh function
const refresh = async () => {
  await fetchRefresh();
};

// Check if filters are active
const hasActiveFilters = computed(() => {
  return (
    searchQuery.value !== "" ||
    selectedStatuses.value.length > 0 ||
    selectedPriorities.value.length > 0
  );
});

// Apply client-side filters
const applyClientFilters = (tasks) => {
  let filtered = tasks;
  if (searchQuery.value) {
    const search = searchQuery.value.toLowerCase();
    filtered = filtered.filter(
      (t) =>
        t.title.toLowerCase().includes(search) ||
        t.description?.toLowerCase().includes(search) ||
        t.project?.name?.toLowerCase().includes(search)
    );
  }
  if (selectedStatuses.value.length > 0) {
    filtered = filtered.filter((t) => selectedStatuses.value.includes(t.status));
  }
  if (selectedPriorities.value.length > 0) {
    filtered = filtered.filter((t) => selectedPriorities.value.includes(t.priority));
  }
  return filtered;
};

// Display computed: returns writable ref value when no filters, filtered otherwise
const inProgressTasks = computed(() => {
  if (!hasActiveFilters.value) return inProgressTasksList.value;
  return applyClientFilters(inProgressTasksList.value);
});

const todoTasks = computed(() => {
  if (!hasActiveFilters.value) return todoTasksList.value;
  return applyClientFilters(todoTasksList.value);
});

const completedTasks = computed(() => {
  if (!hasActiveFilters.value) return completedTasksList.value;
  return applyClientFilters(completedTasksList.value);
});

const deletableCompletedTasks = computed(() =>
  completedTasksList.value.filter((t) => t.can_delete !== false)
);

const pendingTasks = computed(() => [...inProgressTasks.value, ...todoTasks.value]);
const filteredTasks = computed(() => [
  ...inProgressTasks.value,
  ...todoTasks.value,
  ...completedTasks.value,
]);

// Drag and drop
const todoListEl = ref(null);
const inProgressListEl = ref(null);
const completedListEl = ref(null);
const isSyncing = ref(false);

const updateTaskOrder = async (tasksList) => {
  if (isSyncing.value) return;

  try {
    isSyncing.value = true;

    const orders = tasksList.value.map((task, index) => ({
      id: task.id,
      order: index + 1,
    }));

    await client("/api/tasks/update-order", {
      method: "POST",
      body: { orders },
    });

    tasksList.value.forEach((task, index) => {
      task.order_column = index + 1;
    });
  } catch (err) {
    console.error("Failed to update task order:", err);
    toast.error("Failed to update task order");
    await refresh();
  } finally {
    isSyncing.value = false;
  }
};

let todoSortable = null;
let inProgressSortable = null;
let completedSortable = null;

const initializeSortable = () => {
  if (todoSortable?.stop) {
    todoSortable.stop();
    todoSortable = null;
  }
  if (inProgressSortable?.stop) {
    inProgressSortable.stop();
    inProgressSortable = null;
  }
  if (completedSortable?.stop) {
    completedSortable.stop();
    completedSortable = null;
  }

  if (hasActiveFilters.value) return;

  const sortableOptions = (tasksList) => ({
    animation: 200,
    handle: ".drag-handle",
    ghostClass: "sortable-ghost",
    chosenClass: "sortable-chosen",
    dragClass: "sortable-drag",
    onEnd: async () => {
      await nextTick();
      await updateTaskOrder(tasksList);
    },
  });

  nextTick(() => {
    if (todoListEl.value && todoTasksList.value.length > 0) {
      todoSortable = useSortable(todoListEl.value, todoTasksList, sortableOptions(todoTasksList));
    }

    if (inProgressListEl.value && inProgressTasksList.value.length > 0) {
      inProgressSortable = useSortable(
        inProgressListEl.value,
        inProgressTasksList,
        sortableOptions(inProgressTasksList)
      );
    }

    if (completedListEl.value && completedTasksList.value.length > 0) {
      completedSortable = useSortable(
        completedListEl.value,
        completedTasksList,
        sortableOptions(completedTasksList)
      );
    }
  });
};

onMounted(() => {
  initializeSortable();
});

watch(hasActiveFilters, () => {
  initializeSortable();
});

// Update task status with optimistic update
const handleUpdateStatus = async (task, newStatus) => {
  const listMap = {
    in_progress: inProgressTasksList,
    todo: todoTasksList,
    completed: completedTasksList,
  };

  // Find task in current list
  let sourceList = null;
  let sourceIndex = -1;

  for (const list of Object.values(listMap)) {
    const idx = list.value.findIndex((t) => t.id === task.id);
    if (idx !== -1) {
      sourceList = list;
      sourceIndex = idx;
      break;
    }
  }

  if (!sourceList || sourceIndex === -1) return;

  const targetList = listMap[newStatus];
  const oldTask = { ...sourceList.value[sourceIndex] };

  // Optimistic update: move task between lists
  sourceList.value.splice(sourceIndex, 1);
  const updatedTask = {
    ...oldTask,
    status: newStatus,
    completed_at: newStatus === "completed" ? new Date().toISOString() : null,
  };
  targetList.value.push(updatedTask);

  nextTick(() => initializeSortable());

  const statusLabels = {
    completed: "completed",
    todo: "moved to To Do",
    in_progress: "started",
  };

  try {
    await client(`/api/tasks/${task.ulid}`, {
      method: "PUT",
      body: { status: newStatus },
    });

    toast.success(`Task ${statusLabels[newStatus] || newStatus}`);
  } catch (err) {
    // Rollback
    const rIdx = targetList.value.findIndex((t) => t.id === task.id);
    if (rIdx !== -1) targetList.value.splice(rIdx, 1);
    sourceList.value.splice(sourceIndex, 0, oldTask);
    nextTick(() => initializeSortable());

    console.error("Failed to update task status:", err);
    toast.error("Failed to update task status");
  }
};

// ============ Quick Add Task ============
const quickAddInputEl = ref(null);
const quickAddTitle = ref("");
const quickAddLoading = ref(false);

const handleQuickAdd = async () => {
  const title = quickAddTitle.value.trim();
  if (!title || quickAddLoading.value) return;

  quickAddLoading.value = true;
  try {
    const response = await client("/api/tasks", {
      method: "POST",
      body: {
        title,
        status: "todo",
        visibility: "public",
        assignee_id: currentUser.value?.id,
      },
    });

    quickAddTitle.value = "";

    // Add new task directly to the todo list (no refresh needed)
    todoTasksList.value.push(response.data);
    toast.success("Task created");
    nextTick(() => {
      initializeSortable();
      quickAddInputEl.value?.focus();
    });
  } catch (err) {
    console.error("Failed to create task:", err);
    toast.error(err.response?._data?.message || "Failed to create task");
  } finally {
    quickAddLoading.value = false;
  }
};

// ============ Update Task Title (Inline Edit) ============
const handleUpdateTitle = async (task, newTitle) => {
  const oldTitle = task.title;
  task.title = newTitle;

  try {
    await client(`/api/tasks/${task.ulid}`, {
      method: "PUT",
      body: { title: newTitle },
    });
    toast.success("Task updated");
  } catch (err) {
    task.title = oldTitle;
    console.error("Failed to update title:", err);
    toast.error("Failed to update title");
  }
};

// ============ Clear All Completed ============
const deleteMode = ref("single");

const openClearCompletedDialog = () => {
  if (completedTasksList.value.length === 0) return;
  deleteMode.value = "clear-completed";
  dialogs.taskToDelete.value = null;
  dialogs.deleteDialogOpen.value = true;
};

const handleClearCompleted = async () => {
  if (deletableCompletedTasks.value.length === 0 || dialogs.deleteLoading.value) return;

  dialogs.deleteLoading.value = true;
  const ids = deletableCompletedTasks.value.map((t) => t.id);

  try {
    await client("/api/tasks/bulk", {
      method: "DELETE",
      body: { ids },
    });

    completedTasksList.value = completedTasksList.value.filter((t) => !ids.includes(t.id));
    dialogs.deleteDialogOpen.value = false;
    toast.success("Completed tasks cleared");
    nextTick(() => initializeSortable());
  } catch (err) {
    console.error("Failed to clear completed tasks:", err);
    toast.error("Failed to clear completed tasks");
  } finally {
    dialogs.deleteLoading.value = false;
  }
};

// Task dialogs with optimistic delete
const onAfterDelete = (deletedTask) => {
  for (const list of [inProgressTasksList, todoTasksList, completedTasksList]) {
    const idx = list.value.findIndex((t) => t.id === deletedTask.id);
    if (idx !== -1) {
      list.value.splice(idx, 1);
      break;
    }
  }
  nextTick(() => initializeSortable());
};

const dialogs = useTaskDialogs({
  refresh,
  withCreate: true,
  onAfterDelete,
});

// Reset deleteMode when opening single delete via composable
watch(dialogs.deleteDialogOpen, (open) => {
  if (open && dialogs.taskToDelete.value) {
    deleteMode.value = "single";
  }
});

const route = useRoute();
defineShortcuts({
  n: {
    handler: () => {
      if (canCreate.value) {
        dialogs.openCreateDialog();
      }
    },
    whenever: [computed(() => route.path === "/tasks")],
  },
});

// Set page meta
usePageMeta(null, {
  title: "Tasks",
});
</script>
