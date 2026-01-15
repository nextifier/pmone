<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold tracking-tight">Tasks</h1>
      <div class="flex items-center gap-2">
        <NuxtLink
          to="/tasks/trash"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
          <span>Trash</span>
        </NuxtLink>
        <NuxtLink
          to="/tasks/create"
          class="hover:bg-primary/80 text-primary-foreground bg-primary flex items-center gap-x-1.5 rounded-md border px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
        >
          <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
          <span>New Task</span>
        </NuxtLink>
      </div>
    </div>

    <!-- Filters -->
    <div class="border-border bg-card flex flex-wrap items-center gap-3 rounded-lg border p-4">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Search tasks..."
        class="border-border bg-background flex-1 rounded-md border px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-primary"
      />

      <select
        v-model="selectedStatus"
        class="border-border bg-background rounded-md border px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-primary"
      >
        <option value="">All Statuses</option>
        <option value="todo">To Do</option>
        <option value="in_progress">In Progress</option>
        <option value="completed">Completed</option>
        <option value="archived">Archived</option>
      </select>

      <select
        v-model="selectedPriority"
        class="border-border bg-background rounded-md border px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-primary"
      >
        <option value="">All Priorities</option>
        <option value="high">High</option>
        <option value="medium">Medium</option>
        <option value="low">Low</option>
      </select>

      <button
        @click="refresh"
        class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-3 py-1.5 text-sm tracking-tight active:scale-98"
      >
        <Icon name="lucide:refresh-cw" class="size-4 shrink-0" />
        <span>Refresh</span>
      </button>
    </div>

    <!-- Task List -->
    <div v-if="pending" class="flex justify-center py-12">
      <Spinner class="size-8" />
    </div>

    <div v-else-if="error" class="border-border bg-card rounded-lg border p-12 text-center">
      <Icon name="lucide:alert-circle" class="text-destructive mx-auto mb-3 size-12" />
      <p class="text-muted-foreground text-sm">Failed to load tasks. Please try again.</p>
    </div>

    <div
      v-else-if="!filteredTasks || filteredTasks.length === 0"
      class="border-border bg-card rounded-lg border p-12 text-center"
    >
      <Icon name="hugeicons:task-01" class="text-muted-foreground mx-auto mb-3 size-12" />
      <p class="text-muted-foreground mb-4 text-sm">
        {{ searchQuery ? 'No tasks found. Try adjusting your filters.' : 'No tasks yet. Create your first task!' }}
      </p>
      <NuxtLink
        v-if="!searchQuery"
        to="/tasks/create"
        class="bg-primary text-primary-foreground hover:bg-primary/80 inline-flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
      >
        <Icon name="lucide:plus" class="size-4 shrink-0" />
        <span>Create Task</span>
      </NuxtLink>
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="task in filteredTasks"
        :key="task.id"
        class="border-border bg-card hover:border-primary/50 group relative rounded-lg border p-4 transition-colors"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1 space-y-2">
            <div class="flex items-center gap-2">
              <NuxtLink
                :to="`/tasks/${task.ulid}`"
                class="text-foreground group-hover:text-primary font-medium tracking-tight hover:underline"
              >
                {{ task.title }}
              </NuxtLink>
              <span
                v-if="task.priority"
                :class="{
                  'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300': task.priority === 'high',
                  'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300': task.priority === 'medium',
                  'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300': task.priority === 'low',
                }"
                class="rounded-full px-2 py-0.5 text-xs font-medium"
              >
                {{ task.priority }}
              </span>
              <span
                :class="{
                  'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300': task.status === 'todo',
                  'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300': task.status === 'in_progress',
                  'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300': task.status === 'completed',
                  'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-500': task.status === 'archived',
                }"
                class="rounded-full px-2 py-0.5 text-xs font-medium"
              >
                {{ task.status.replace('_', ' ') }}
              </span>
            </div>

            <div class="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
              <span v-if="task.assignee" class="flex items-center gap-1">
                <Icon name="lucide:user" class="size-3" />
                {{ task.assignee.name }}
              </span>
              <span v-if="task.project" class="flex items-center gap-1">
                <Icon name="lucide:folder" class="size-3" />
                {{ task.project.name }}
              </span>
              <span v-if="task.estimated_completion_at" class="flex items-center gap-1">
                <Icon name="lucide:calendar" class="size-3" />
                {{ formatDate(task.estimated_completion_at) }}
                <span v-if="task.is_overdue" class="text-destructive ml-1">(Overdue)</span>
              </span>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <NuxtLink
              :to="`/tasks/${task.ulid}/edit`"
              class="hover:bg-muted rounded-md p-1.5 transition-colors"
              title="Edit"
            >
              <Icon name="lucide:pencil" class="size-4" />
            </NuxtLink>
            <button
              @click="openDeleteDialog(task)"
              class="hover:bg-destructive/10 text-destructive rounded-md p-1.5 transition-colors"
              title="Delete"
            >
              <Icon name="lucide:trash" class="size-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Dialog -->
    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">Delete Task?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            Are you sure you want to delete <strong>{{ taskToDelete?.title }}</strong>? This action can be undone from trash.
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <button
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              @click="deleteDialogOpen = false"
            >
              Cancel
            </button>
            <button
              @click="handleDeleteTask"
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

definePageMeta({
  middleware: ['auth', 'verified'],
  layout: 'default',
});

const searchQuery = ref('');
const selectedStatus = ref('');
const selectedPriority = ref('');
const deleteDialogOpen = ref(false);
const taskToDelete = ref(null);
const deleteLoading = ref(false);

// Fetch tasks
const { data: tasks, pending, error, refresh } = await useFetch('/api/tasks', {
  query: computed(() => ({
    filter_search: searchQuery.value || undefined,
    filter_status: selectedStatus.value || undefined,
    filter_priority: selectedPriority.value || undefined,
  })),
});

const filteredTasks = computed(() => tasks.value?.data || []);

const formatDate = (date) => {
  if (!date) return '';
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};

const openDeleteDialog = (task) => {
  taskToDelete.value = task;
  deleteDialogOpen.value = true;
};

const handleDeleteTask = async () => {
  if (!taskToDelete.value) return;

  deleteLoading.value = true;
  try {
    await $fetch(`/api/tasks/${taskToDelete.value.ulid}`, {
      method: 'DELETE',
    });

    // Refresh the list
    await refresh();

    // Close dialog
    deleteDialogOpen.value = false;
    taskToDelete.value = null;

    // Show success toast (if you have toast component)
    // toast.success('Task deleted successfully');
  } catch (error) {
    console.error('Failed to delete task:', error);
    // toast.error('Failed to delete task');
  } finally {
    deleteLoading.value = false;
  }
};
</script>
