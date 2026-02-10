<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <!-- Header -->
    <TasksHeader title="All Tasks" icon="hugeicons:user-group">
      <template #actions>
        <NuxtLink
          to="/tasks"
          class="border-border hover:bg-muted flex items-center gap-x-1.5 rounded-md border px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:task-daily-01" class="size-4 shrink-0" />
          <span>My Tasks</span>
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
    />

    <!-- Loading State -->
    <div v-if="pending" class="flex justify-center py-12">
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
      v-else-if="filteredGroups.length === 0"
      class="flex flex-col items-center justify-center pt-10 text-center"
    >
      <Icon name="hugeicons:user-group" class="text-muted-foreground mx-auto mb-3 size-12" />
      <span class="text-base font-semibold tracking-tight">No tasks found.</span>
      <span class="text-muted-foreground mt-1 text-sm">
        {{ searchQuery ? "Try adjusting your search query." : "There are no tasks to display." }}
      </span>
    </div>

    <!-- Tasks Content -->
    <template v-else>
      <!-- Show Details Toggle -->
      <div class="flex items-center gap-x-2">
        <Switch
          id="show-details-all"
          :model-value="showDetails"
          @update:model-value="toggleShowDetails"
        />
        <Label for="show-details-all" class="cursor-pointer text-sm tracking-tight"
          >Show Details</Label
        >
      </div>

      <!-- User Groups -->
      <Accordion type="multiple" :default-value="defaultOpenItems">
        <AccordionItem
          v-for="group in filteredGroups"
          :key="group.assignee?.id || 'unassigned'"
          :value="String(group.assignee?.id || 'unassigned')"
        >
          <AccordionTrigger>
            <div class="flex items-center gap-3">
              <Avatar
                v-if="group.assignee"
                :model="group.assignee"
                size="sm"
                class="size-9"
                rounded="rounded-full"
              />
              <div v-else class="bg-muted flex size-9 items-center justify-center rounded-full">
                <Icon name="hugeicons:user" class="text-muted-foreground size-4" />
              </div>

              <div class="flex flex-col items-start">
                <span class="text-sm font-semibold tracking-tight">
                  {{ group.assignee?.name || "Unassigned" }}
                </span>
                <span v-if="group.assignee?.username" class="text-muted-foreground text-xs">
                  @{{ group.assignee.username }}
                </span>
              </div>

              <Badge variant="secondary" class="ml-2">
                {{ group.filteredCount }}
                {{ group.filteredCount === 1 ? "task" : "tasks" }}
              </Badge>
            </div>
          </AccordionTrigger>

          <AccordionContent>
            <!-- 2-Column Layout: Active (left) | Completed (right) -->
            <div class="grid grid-cols-1 items-start gap-x-3 gap-y-5 pt-2 lg:grid-cols-2">
              <!-- Left Column: In Progress + To Do -->
              <div class="border-border bg-card rounded-xl border">
                <div class="flex flex-col divide-y">
                  <!-- In Progress Section -->
                  <div v-if="group.inProgress.length > 0" class="flex flex-col gap-y-2 px-3 py-6">
                    <div class="flex items-center gap-x-2">
                      <Icon name="hugeicons:loading-03" class="text-info-foreground size-4.5" />
                      <span class="text-sm font-medium tracking-tight">In Progress</span>
                      <Badge variant="secondary" class="h-4 px-1.5 text-[10px]">
                        {{ group.inProgress.length }}
                      </Badge>
                    </div>
                    <div>
                      <TaskCard
                        v-for="task in group.inProgress"
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
                  <div v-if="group.todo.length > 0" class="flex flex-col gap-y-2 px-3 py-6">
                    <div class="flex items-center gap-x-2">
                      <Icon name="hugeicons:task-daily-01" class="text-muted-foreground size-4.5" />
                      <span class="text-sm font-medium tracking-tight">To Do</span>
                      <Badge variant="secondary" class="h-4 px-1.5 text-[10px]">
                        {{ group.todo.length }}
                      </Badge>
                    </div>
                    <div>
                      <TaskCard
                        v-for="task in group.todo"
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

                  <!-- Empty pending -->
                  <div
                    v-if="group.inProgress.length === 0 && group.todo.length === 0"
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
                        {{ group.completed.length }}
                      </Badge>
                    </div>
                    <div v-if="group.completed.length > 0">
                      <TaskCard
                        v-for="task in group.completed"
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
          </AccordionContent>
        </AccordionItem>
      </Accordion>
    </template>

    <!-- Edit Task Dialog -->
    <DialogResponsive
      v-model:open="editDialogOpen"
      dialog-max-width="600px"
      :overflow-content="true"
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
import Avatar from "@/components/Avatar.vue";
import DialogResponsive from "@/components/DialogResponsive.vue";
import FormTask from "@/components/FormTask.vue";
import TaskCard from "@/components/task/TaskCard.vue";
import TaskDetailDialog from "@/components/task/TaskDetailDialog.vue";
import TasksFilters from "@/components/task/TasksFilters.vue";
import TasksHeader from "@/components/task/TasksHeader.vue";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const client = useSanctumClient();

// Show details toggle (shared with tasks/index)
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

// Fetch all tasks grouped by user
const {
  data: response,
  pending,
  error,
  refresh,
} = await useLazySanctumFetch("/api/tasks/all", {
  key: "tasks-all",
});

const groupedData = computed(() => response.value?.data || []);

// Client-side filtering
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

// Filtered and structured groups
const filteredGroups = computed(() => {
  return groupedData.value
    .map((group) => {
      const tasks = applyClientFilters(group.tasks);
      return {
        ...group,
        filteredCount: tasks.length,
        inProgress: tasks
          .filter((t) => t.status === "in_progress")
          .sort((a, b) => (a.order_column || 0) - (b.order_column || 0)),
        todo: tasks
          .filter((t) => t.status === "todo")
          .sort((a, b) => (a.order_column || 0) - (b.order_column || 0)),
        completed: tasks
          .filter((t) => t.status === "completed")
          .sort((a, b) => {
            const dateA = a.completed_at ? new Date(a.completed_at) : new Date(0);
            const dateB = b.completed_at ? new Date(b.completed_at) : new Date(0);
            return dateB - dateA;
          }),
      };
    })
    .filter((group) => group.filteredCount > 0);
});

// Default open all items
const defaultOpenItems = computed(() => {
  return filteredGroups.value.map((g) => String(g.assignee?.id || "unassigned"));
});

// Update task status
const handleUpdateStatus = async (task, newStatus) => {
  try {
    await client(`/api/tasks/${task.ulid}`, {
      method: "PUT",
      body: { status: newStatus },
    });

    await refresh();

    const statusLabels = {
      completed: "completed",
      todo: "moved to To Do",
      in_progress: "started",
    };
    toast.success(`Task ${statusLabels[newStatus] || newStatus}`);
  } catch (err) {
    console.error("Failed to update task status:", err);
    toast.error("Failed to update task status");
  }
};

// Update task title (inline edit)
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

// ============ Delete Task Dialog ============
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
    await client(`/api/tasks/${taskToDelete.value.ulid}`, {
      method: "DELETE",
    });

    await refresh();
    deleteDialogOpen.value = false;
    taskToDelete.value = null;
    toast.success("Task deleted");
  } catch (err) {
    console.error("Failed to delete task:", err);
    toast.error("Failed to delete task");
  } finally {
    deleteLoading.value = false;
  }
};

useHead({
  title: "All Tasks",
});
</script>
