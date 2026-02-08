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
      v-else-if="groupedData.length === 0"
      class="flex flex-col items-center justify-center pt-10 text-center"
    >
      <Icon name="hugeicons:user-group" class="text-muted-foreground mx-auto mb-3 size-12" />
      <span class="text-base font-semibold tracking-tight">No tasks found.</span>
      <span class="text-muted-foreground mt-1 text-sm"> There are no tasks to display. </span>
    </div>

    <!-- Accordion grouped by user -->
    <Accordion v-else type="multiple" :default-value="defaultOpenItems">
      <AccordionItem
        v-for="group in groupedData"
        :key="group.assignee?.id || 'unassigned'"
        :value="String(group.assignee?.id || 'unassigned')"
      >
        <AccordionTrigger>
          <div class="flex items-center gap-3">
            <!-- User Avatar -->
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

            <!-- User Info -->
            <div class="flex flex-col items-start">
              <span class="text-sm font-semibold tracking-tight">
                {{ group.assignee?.name || "Unassigned" }}
              </span>
              <span v-if="group.assignee?.username" class="text-muted-foreground text-xs">
                @{{ group.assignee.username }}
              </span>
            </div>

            <!-- Task Count -->
            <Badge variant="secondary" class="ml-2">
              {{ group.count }} {{ group.count === 1 ? "task" : "tasks" }}
            </Badge>
          </div>
        </AccordionTrigger>

        <AccordionContent>
          <div class="space-y-2">
            <TaskCard
              v-for="task in group.tasks"
              :key="task.id"
              :task="task"
              @update-status="handleUpdateStatus"
              @delete="openDeleteDialog"
              @view="openDetailDialog"
              @edit="openEditDialog"
            />
          </div>

          <!-- View user's tasks link -->
          <NuxtLink
            v-if="group.assignee?.username"
            :to="`/${group.assignee.username}/tasks`"
            class="text-primary hover:text-primary/80 mt-3 flex items-center gap-x-1 text-sm font-medium tracking-tight hover:underline"
          >
            <span>View {{ group.assignee.name }}'s tasks</span>
            <Icon name="hugeicons:arrow-right-01" class="size-4 shrink-0" />
          </NuxtLink>
        </AccordionContent>
      </AccordionItem>
    </Accordion>

    <!-- Edit Task Dialog -->
    <DialogResponsive v-model:open="editDialogOpen" dialog-max-width="600px">
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

    <!-- Detail Task Dialog -->
    <DialogResponsive v-model:open="detailDialogOpen" dialog-max-width="550px">
      <template #default>
        <TaskDetailDialog
          v-if="taskToView"
          :task="taskToView"
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
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const client = useSanctumClient();

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

// Default open the first item
const defaultOpenItems = computed(() => {
  if (groupedData.value.length > 0) {
    return [String(groupedData.value[0]?.assignee?.id || "unassigned")];
  }
  return [];
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
    toast.success("Task deleted successfully");
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
