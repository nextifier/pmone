<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <!-- Header -->
    <TasksHeader :title="pageTitle" icon="hugeicons:task-daily-01">
      <template #actions>
        <NuxtLink
          to="/tasks"
          class="border-border hover:bg-muted flex items-center gap-x-1.5 rounded-md border px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:arrow-left-01" class="size-4 shrink-0" />
          <span>My Tasks</span>
        </NuxtLink>
      </template>
    </TasksHeader>

    <!-- User Info Card -->
    <div v-if="targetUser" class="border-border bg-card rounded-xl border p-4">
      <div class="flex items-center gap-3">
        <Avatar :model="targetUser" size="sm" class="size-14" rounded="rounded-full" />
        <div class="flex flex-col gap-y-0.5">
          <span class="text-lg font-semibold tracking-tight">{{ targetUser.name }}</span>
          <span v-if="targetUser.title" class="text-muted-foreground text-sm">
            {{ targetUser.title }}
          </span>
          <span class="text-muted-foreground text-xs">@{{ targetUser.username }}</span>
        </div>
        <div class="ml-auto">
          <Badge variant="secondary">
            {{ meta?.total || 0 }} {{ (meta?.total || 0) === 1 ? "task" : "tasks" }}
          </Badge>
        </div>
      </div>
    </div>

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
      v-else-if="tasks.length === 0"
      class="flex flex-col items-center justify-center pt-10 text-center"
    >
      <Icon name="hugeicons:task-daily-01" class="text-muted-foreground mx-auto mb-3 size-12" />
      <span class="text-base font-semibold tracking-tight">No public tasks</span>
      <span class="text-muted-foreground mt-1 text-sm">
        This user doesn't have any visible tasks.
      </span>
    </div>

    <!-- Tasks by Status -->
    <div v-else class="space-y-8">
      <!-- In Progress Tasks -->
      <div v-if="inProgressTasks.length > 0" class="space-y-3">
        <div class="flex items-center gap-2">
          <TaskLoaderBars />
          <h2 class="text-sm font-semibold tracking-tight">In Progress</h2>
          <Badge variant="secondary" class="text-xs">{{ inProgressTasks.length }}</Badge>
        </div>
        <div class="space-y-2">
          <TaskCard
            v-for="task in inProgressTasks"
            :key="task.id"
            :task="task"
            :can-edit="task.can_edit === true"
            @update-status="handleUpdateStatus"
            @delete="openDeleteDialog"
            @view="openDetailDialog"
            @edit="openEditDialog"
          />
        </div>
      </div>

      <!-- To Do Tasks -->
      <div v-if="todoTasks.length > 0" class="space-y-3">
        <div class="flex items-center gap-2">
          <Icon name="hugeicons:task-daily-01" class="text-muted-foreground size-4" />
          <h2 class="text-sm font-semibold tracking-tight">To Do</h2>
          <Badge variant="secondary" class="text-xs">{{ todoTasks.length }}</Badge>
        </div>
        <div class="space-y-2">
          <TaskCard
            v-for="task in todoTasks"
            :key="task.id"
            :task="task"
            :can-edit="task.can_edit === true"
            @update-status="handleUpdateStatus"
            @delete="openDeleteDialog"
            @view="openDetailDialog"
            @edit="openEditDialog"
          />
        </div>
      </div>

      <!-- Completed Tasks -->
      <div v-if="completedTasks.length > 0" class="space-y-3">
        <div class="flex items-center gap-2">
          <Icon name="hugeicons:tick-02" class="size-4 text-green-600 dark:text-green-500" />
          <h2 class="text-sm font-semibold tracking-tight">Completed</h2>
          <Badge variant="secondary" class="text-xs">{{ completedTasks.length }}</Badge>
        </div>
        <div class="space-y-2">
          <TaskCard
            v-for="task in completedTasks"
            :key="task.id"
            :task="task"
            :can-edit="task.can_edit === true"
            @update-status="handleUpdateStatus"
            @delete="openDeleteDialog"
            @view="openDetailDialog"
            @edit="openEditDialog"
          />
        </div>
      </div>

      <!-- Archived Tasks -->
      <div v-if="archivedTasks.length > 0" class="space-y-3">
        <div class="flex items-center gap-2">
          <Icon name="hugeicons:archive-02" class="text-muted-foreground size-4" />
          <h2 class="text-sm font-semibold tracking-tight">Archived</h2>
          <Badge variant="secondary" class="text-xs">{{ archivedTasks.length }}</Badge>
        </div>
        <div class="space-y-2">
          <TaskCard
            v-for="task in archivedTasks"
            :key="task.id"
            :task="task"
            :can-edit="task.can_edit === true"
            @update-status="handleUpdateStatus"
            @delete="openDeleteDialog"
            @view="openDetailDialog"
            @edit="openEditDialog"
          />
        </div>
      </div>
    </div>

    <!-- Edit Task Dialog -->
    <DialogResponsive
      v-model:open="editDialogOpen"
      dialog-max-width="600px"
      :prevent-close="editFormRef?.isDirty ?? false"
      @close-prevented="unsavedDialogOpen = true"
    >
      <template #sticky-header>
        <div
          class="border-border bg-background/95 sticky top-0 z-10 border-b px-4 py-4 backdrop-blur md:px-6"
        >
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
    <DialogResponsive v-model:open="unsavedDialogOpen">
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
    <DialogResponsive v-model:open="detailDialogOpen" dialog-max-width="550px">
      <template #default>
        <TaskDetailDialog
          v-if="taskToView"
          :task="taskToView"
          :can-edit="taskToView.can_edit === true"
          @close="detailDialogOpen = false"
          @edit="handleEditFromDetail"
        />
      </template>
    </DialogResponsive>

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
import Avatar from "@/components/Avatar.vue";
import DialogResponsive from "@/components/DialogResponsive.vue";
import FormTask from "@/components/FormTask.vue";
import TaskCard from "@/components/task/TaskCard.vue";
import TaskDetailDialog from "@/components/task/TaskDetailDialog.vue";
import TasksHeader from "@/components/task/TasksHeader.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["tasks.read"],
  layout: "app",
});

const route = useRoute();
const username = route.params.username;
const client = useSanctumClient();

// Fetch user's tasks
const {
  data: response,
  pending,
  error,
  refresh,
} = await useLazySanctumFetch(`/api/users/${username}/tasks`, {
  query: { per_page: 50 },
  key: `user-tasks-${username}`,
});

const tasks = computed(() => response.value?.data || []);
const meta = computed(() => response.value?.meta);
const targetUser = computed(() => response.value?.user);

const pageTitle = computed(() => {
  return targetUser.value ? `${targetUser.value.name}'s Tasks` : "User Tasks";
});

// Group tasks by status
const inProgressTasks = computed(() => tasks.value.filter((t) => t.status === "in_progress"));
const todoTasks = computed(() => tasks.value.filter((t) => t.status === "todo"));
const completedTasks = computed(() => tasks.value.filter((t) => t.status === "completed"));
const archivedTasks = computed(() => tasks.value.filter((t) => t.status === "archived"));

// Update task status
const handleUpdateStatus = async (task, newStatus) => {
  try {
    await client(`/api/tasks/${task.ulid}`, {
      method: "PUT",
      body: { status: newStatus },
    });

    await refresh();
    toast.success(`Task marked as ${newStatus.replace("_", " ")}`);
  } catch (err) {
    console.error("Failed to update task status:", err);
    toast.error("Failed to update task status");
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

const handleUnsavedSave = () => {
  unsavedDialogOpen.value = false;
  editFormRef.value?.handleSubmit();
};

const handleUnsavedDiscard = () => {
  unsavedDialogOpen.value = false;
  editDialogOpen.value = false;
  taskToEdit.value = null;
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
    toast.success("Task deleted successfully");
  } catch (err) {
    console.error("Failed to delete task:", err);
    toast.error("Failed to delete task");
  } finally {
    deleteLoading.value = false;
  }
};

useHead({
  title: pageTitle,
});
</script>
