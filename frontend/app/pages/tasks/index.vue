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
          @click="openCreateDialog"
          class="hover:bg-primary/80 text-primary-foreground bg-primary flex items-center gap-x-1.5 rounded-md border px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:plus-sign" class="-ml-1 size-4 shrink-0" />
          <span>Add task</span>
        </button>
      </template>
    </TasksFilters>

    <!-- Loading State -->
    <div v-if="pending && !tasksResponse?.data" class="flex justify-center py-12">
      <Spinner class="size-8" />
    </div>

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
        @click="openCreateDialog"
        class="bg-primary text-primary-foreground hover:bg-primary/80 mt-4 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
      >
        <Icon name="hugeicons:plus-sign" class="size-4 shrink-0" />
        <span>Create Task</span>
      </button>
    </div>

    <!-- Tasks Content -->
    <template v-else>
      <!-- Show Details Toggle -->
      <div class="flex items-center gap-x-2">
        <Switch
          id="show-details"
          :model-value="showDetails"
          @update:model-value="toggleShowDetails"
        />
        <Label for="show-details" class="cursor-pointer text-sm tracking-tight">Show Details</Label>
      </div>

      <!-- 2-Column Layout: Active (left) | Completed (right) -->
      <div class="grid grid-cols-1 items-start gap-x-3 gap-y-5 lg:grid-cols-2">
        <!-- Left Column: In Progress + To Do -->
        <div class="border-border bg-card rounded-xl border">
          <div class="flex flex-col divide-y">
            <!-- In Progress Section -->
            <div v-if="inProgressTasks.length > 0" class="flex flex-col gap-y-2 px-3 py-6">
              <div class="flex items-center gap-x-2">
                <Icon name="hugeicons:loading-03" class="text-info-foreground size-4.5" />
                <span class="text-sm font-medium tracking-tight">In Progress</span>
                <Badge variant="secondary" class="h-4 px-1.5 text-[10px]">
                  {{ inProgressTasks.length }}
                </Badge>
              </div>
              <div ref="inProgressListEl">
                <TaskCard
                  v-for="task in inProgressTasks"
                  :key="task.id"
                  :task="task"
                  :show-details="showDetails"
                  :can-edit="task.can_edit !== false"
                  @update-status="handleUpdateStatus"
                  @update-title="handleUpdateTitle"
                  @delete="openDeleteDialog"
                  @view="openDetailDialog"
                  @edit="openEditDialog"
                />
              </div>
            </div>

            <!-- To Do Section -->
            <div v-if="todoTasks.length > 0" class="flex flex-col gap-y-2 px-3 py-6">
              <div class="flex items-center gap-x-2">
                <Icon name="hugeicons:task-daily-01" class="text-muted-foreground size-4.5" />
                <span class="text-sm font-medium tracking-tight">To Do</span>
                <Badge variant="secondary" class="h-4 px-1.5 text-[10px]">
                  {{ todoTasks.length }}
                </Badge>
              </div>
              <div ref="todoListEl">
                <TaskCard
                  v-for="task in todoTasks"
                  :key="task.id"
                  :task="task"
                  :show-details="showDetails"
                  :can-edit="task.can_edit !== false"
                  @update-status="handleUpdateStatus"
                  @update-title="handleUpdateTitle"
                  @delete="openDeleteDialog"
                  @view="openDetailDialog"
                  @edit="openEditDialog"
                />
              </div>

              <!-- Quick Add -->
              <div v-if="canCreate" class="flex items-start gap-x-3.5 px-2.5 py-1">
                <Icon
                  name="hugeicons:plus-sign"
                  class="text-muted-foreground mt-1.5 size-4 shrink-0"
                />
                <textarea
                  ref="quickAddInputEl"
                  v-model="quickAddTitle"
                  rows="1"
                  placeholder="Add task..."
                  class="text-foreground placeholder:text-muted-foreground/60 field-sizing-content w-full resize-none bg-transparent text-base tracking-tight outline-none"
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
              <Icon name="hugeicons:inbox" class="text-muted-foreground/50 mb-2 size-8" />
              <span class="text-muted-foreground text-xs">No active tasks</span>
            </div>
          </div>
        </div>

        <!-- Right Column: Completed -->
        <div class="border-border bg-card rounded-xl border">
          <div class="flex flex-col divide-y">
            <div class="flex flex-col gap-y-2 px-3 py-6">
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
                  class="text-muted-foreground hover:text-foreground ml-auto text-sm tracking-tight hover:underline"
                >
                  Clear all
                </button>
              </div>
              <div v-if="completedTasks.length > 0" ref="completedListEl">
                <TaskCard
                  v-for="task in completedTasks"
                  :key="task.id"
                  :task="task"
                  :show-details="showDetails"
                  :can-edit="task.can_edit !== false"
                  @update-status="handleUpdateStatus"
                  @update-title="handleUpdateTitle"
                  @delete="openDeleteDialog"
                  @view="openDetailDialog"
                  @edit="openEditDialog"
                />
              </div>
              <div v-else class="flex flex-col items-center justify-center py-8 text-center">
                <Icon
                  name="hugeicons:checkmark-circle-02"
                  class="text-muted-foreground/50 mb-2 size-8"
                />
                <span class="text-muted-foreground text-xs">No completed tasks</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Create Task Dialog -->
    <DialogResponsive
      v-model:open="createDialogOpen"
      dialog-max-width="600px"
      :overflow-content="true"
      :prevent-close="createFormRef?.isDirty ?? false"
      @close-prevented="handleCreateClosePrevented"
    >
      <template #sticky-header>
        <div class="border-border sticky top-0 z-10 border-b px-4 pb-4 md:px-6 md:py-4">
          <div class="text-lg font-semibold tracking-tight">Create New Task</div>
          <p class="text-muted-foreground text-sm">Add a new task to your list</p>
        </div>
      </template>
      <template #default>
        <div class="px-4 py-4 md:px-6">
          <FormTask
            ref="createFormRef"
            :loading="createLoading"
            @submit="handleCreateTask"
            @cancel="createDialogOpen = false"
          />
        </div>
      </template>
    </DialogResponsive>

    <!-- Edit Task Dialog -->
    <DialogResponsive
      v-model:open="editDialogOpen"
      dialog-max-width="600px"
      :overflow-content="true"
      :prevent-close="editFormRef?.isDirty ?? false"
      @close-prevented="handleEditClosePrevented"
    >
      <template #sticky-header>
        <div class="border-border sticky top-0 z-10 border-b px-4 pb-4 md:px-6 md:py-4">
          <div class="text-lg font-semibold tracking-tight">Edit Task</div>
          <p class="text-muted-foreground text-sm">Update task details</p>
        </div>
      </template>
      <template #default>
        <div class="px-4 py-4 md:px-6">
          <FormTask
            v-if="taskToEdit"
            ref="editFormRef"
            :task="taskToEdit"
            :loading="editLoading"
            @submit="handleEditTask"
            @cancel="editDialogOpen = false"
          />
        </div>
      </template>
    </DialogResponsive>

    <!-- Unsaved Changes Dialog -->
    <DialogResponsive v-model:open="unsavedDialogOpen" :hide-overlay="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-6">
          <div class="text-foreground text-lg font-semibold tracking-tight">Unsaved Changes</div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            You have unsaved changes. Would you like to save them before closing?
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <Button variant="outline" @click="handleUnsavedDiscard">Discard</Button>
            <Button @click="handleUnsavedSave">Save</Button>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Detail Task Dialog -->
    <DialogResponsive
      v-model:open="detailDialogOpen"
      dialog-max-width="600px"
      :overflow-content="true"
    >
      <template #default>
        <TaskDetailDialog
          v-if="taskToView"
          :task="taskToView"
          :can-edit="taskToView.can_edit !== false"
          @close="detailDialogOpen = false"
          @edit="handleEditFromDetail"
        />
      </template>
    </DialogResponsive>

    <!-- Delete Dialog -->
    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-6">
          <template v-if="deleteMode === 'clear-completed'">
            <div class="text-foreground text-lg font-semibold tracking-tight">
              Clear Completed Tasks?
            </div>
            <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
              Are you sure you want to delete
              <strong>{{ deletableCompletedTasks.length }} completed tasks</strong>? This action can be
              undone from trash.
            </p>
          </template>
          <template v-else>
            <div class="text-foreground text-lg font-semibold tracking-tight">Delete Task?</div>
            <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
              Are you sure you want to delete <strong>{{ taskToDelete?.title }}</strong
              >? This action can be undone from trash.
            </p>
          </template>
          <div class="mt-4 flex justify-end gap-2">
            <Button variant="outline" @click="deleteDialogOpen = false"> Cancel </Button>
            <Button
              variant="destructive"
              @click="
                deleteMode === 'clear-completed' ? handleClearCompleted() : handleDeleteTask()
              "
              :disabled="deleteLoading"
            >
              <Spinner v-if="deleteLoading" class="size-4" />
              <span v-else>Delete</span>
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Floating Add Task Button -->
    <div
      v-if="canCreate"
      class="xs:right-[calc(var(--spacing)*4+var(--scrollbar-width,0px))] fixed right-[calc(var(--spacing)*3+var(--scrollbar-width,0px))] bottom-8 z-50 sm:right-[calc(var(--spacing)*6+var(--scrollbar-width,0px))] sm:bottom-5 lg:bottom-12 xl:right-[calc(var(--spacing)*12+var(--scrollbar-width,0px))]"
    >
      <GlassButton variant="default" size="icon-xl" @click="openCreateDialog">
        <Icon name="hugeicons:plus-sign" class="size-5 shrink-0" />
      </GlassButton>
    </div>
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import FormTask from "@/components/FormTask.vue";
import TaskCard from "@/components/task/TaskCard.vue";
import TaskDetailDialog from "@/components/task/TaskDetailDialog.vue";
import TasksFilters from "@/components/task/TasksFilters.vue";
import TasksHeader from "@/components/task/TasksHeader.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { GlassButton } from "@/components/ui/glass-button";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
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

// ============ Create Task Dialog ============
const createDialogOpen = ref(false);
const createFormRef = ref(null);
const createLoading = ref(false);

const openCreateDialog = () => {
  createFormRef.value?.resetForm();
  createDialogOpen.value = true;
  nextTick(() => {
    createFormRef.value?.focusTitle();
  });
};

const handleCreateTask = async (payload) => {
  createLoading.value = true;
  try {
    await client("/api/tasks", {
      method: "POST",
      body: payload,
    });

    await refresh();
    createDialogOpen.value = false;
    toast.success("Task created successfully");
  } catch (err) {
    console.error("Failed to create task:", err);
    if (err.response?._data?.errors) {
      createFormRef.value?.setErrors(err.response._data.errors);
    }
    toast.error(err.response?._data?.message || "Failed to create task");
  } finally {
    createLoading.value = false;
  }
};

// ============ Edit Task Dialog ============
const editDialogOpen = ref(false);
const editFormRef = ref(null);
const editLoading = ref(false);
const taskToEdit = ref(null);

const openEditDialog = (task) => {
  taskToEdit.value = task;
  editDialogOpen.value = true;
};

const handleEditTask = async (payload) => {
  if (!taskToEdit.value) return;

  editLoading.value = true;
  try {
    await client(`/api/tasks/${taskToEdit.value.ulid}`, {
      method: "PUT",
      body: payload,
    });

    await refresh();
    editDialogOpen.value = false;
    taskToEdit.value = null;
    toast.success("Task updated successfully");
  } catch (err) {
    console.error("Failed to update task:", err);
    if (err.response?._data?.errors) {
      editFormRef.value?.setErrors(err.response._data.errors);
    }
    toast.error(err.response?._data?.message || "Failed to update task");
  } finally {
    editLoading.value = false;
  }
};

// ============ Detail Task Dialog ============
const detailDialogOpen = ref(false);
const taskToView = ref(null);

const openDetailDialog = (task) => {
  taskToView.value = task;
  detailDialogOpen.value = true;
};

const handleEditFromDetail = (task) => {
  detailDialogOpen.value = false;
  nextTick(() => {
    openEditDialog(task);
  });
};

// ============ Unsaved Changes Dialog ============
const unsavedDialogOpen = ref(false);
const unsavedContext = ref(null); // 'create' or 'edit'
const unsavedPayload = ref(null);

// Capture payload while form ref is still alive (before drawer unmounts content)
const handleCreateClosePrevented = () => {
  unsavedPayload.value = createFormRef.value?.getPayload?.() || null;
  unsavedContext.value = "create";
  unsavedDialogOpen.value = true;
};

const handleEditClosePrevented = () => {
  unsavedPayload.value = editFormRef.value?.getPayload?.() || null;
  unsavedContext.value = "edit";
  unsavedDialogOpen.value = true;
};

const handleUnsavedSave = () => {
  unsavedDialogOpen.value = false;
  if (unsavedPayload.value) {
    if (unsavedContext.value === "create") {
      handleCreateTask(unsavedPayload.value);
    } else {
      handleEditTask(unsavedPayload.value);
    }
  }
  unsavedPayload.value = null;
};

const handleUnsavedDiscard = () => {
  unsavedDialogOpen.value = false;
  unsavedPayload.value = null;
  if (unsavedContext.value === "create") {
    createDialogOpen.value = false;
    createFormRef.value?.resetForm();
  } else {
    editDialogOpen.value = false;
    taskToEdit.value = null;
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
const handleClearCompleted = async () => {
  if (deletableCompletedTasks.value.length === 0 || deleteLoading.value) return;

  deleteLoading.value = true;
  const ids = deletableCompletedTasks.value.map((t) => t.id);

  try {
    await client("/api/tasks/bulk", {
      method: "DELETE",
      body: { ids },
    });

    completedTasksList.value = completedTasksList.value.filter(
      (t) => !ids.includes(t.id)
    );
    deleteDialogOpen.value = false;
    toast.success("Completed tasks cleared");
    nextTick(() => initializeSortable());
  } catch (err) {
    console.error("Failed to clear completed tasks:", err);
    toast.error("Failed to clear completed tasks");
  } finally {
    deleteLoading.value = false;
  }
};

// ============ Delete Task Dialog ============
const deleteDialogOpen = ref(false);
const taskToDelete = ref(null);
const deleteLoading = ref(false);
const deleteMode = ref("single"); // "single" or "clear-completed"

const openDeleteDialog = (task) => {
  deleteMode.value = "single";
  taskToDelete.value = task;
  deleteDialogOpen.value = true;
};

const openClearCompletedDialog = () => {
  if (completedTasksList.value.length === 0) return;
  deleteMode.value = "clear-completed";
  taskToDelete.value = null;
  deleteDialogOpen.value = true;
};

const handleDeleteTask = async () => {
  if (!taskToDelete.value) return;

  deleteLoading.value = true;
  const taskId = taskToDelete.value.id;

  try {
    await client(`/api/tasks/${taskToDelete.value.ulid}`, {
      method: "DELETE",
    });

    // Optimistic: remove from local lists
    for (const list of [inProgressTasksList, todoTasksList, completedTasksList]) {
      const idx = list.value.findIndex((t) => t.id === taskId);
      if (idx !== -1) {
        list.value.splice(idx, 1);
        break;
      }
    }

    deleteDialogOpen.value = false;
    taskToDelete.value = null;
    toast.success("Task deleted");
    nextTick(() => initializeSortable());
  } catch (err) {
    console.error("Failed to delete task:", err);
    toast.error("Failed to delete task");
  } finally {
    deleteLoading.value = false;
  }
};

const route = useRoute();
defineShortcuts({
  n: {
    handler: () => {
      if (canCreate.value) {
        openCreateDialog();
      }
    },
    whenever: [computed(() => route.path === "/tasks")],
  },
});

// Set page meta
useHead({
  title: "Tasks",
});
</script>
