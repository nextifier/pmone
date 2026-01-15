<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold tracking-tight">Trash</h1>
        <p class="text-muted-foreground mt-1 text-sm">
          Deleted tasks can be restored or permanently deleted
        </p>
      </div>
      <BackButton />
    </div>

    <!-- Task List -->
    <div v-if="pending" class="flex justify-center py-12">
      <Spinner class="size-8" />
    </div>

    <div v-else-if="error" class="border-border bg-card rounded-lg border p-12 text-center">
      <Icon name="lucide:alert-circle" class="text-destructive mx-auto mb-3 size-12" />
      <p class="text-muted-foreground text-sm">Failed to load deleted tasks. Please try again.</p>
    </div>

    <div
      v-else-if="!trashedTasks || trashedTasks.length === 0"
      class="border-border bg-card rounded-lg border p-12 text-center"
    >
      <Icon name="lucide:trash-2" class="text-muted-foreground mx-auto mb-3 size-12" />
      <p class="text-muted-foreground text-sm">No deleted tasks found.</p>
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="task in trashedTasks"
        :key="task.id"
        class="border-border bg-card hover:border-primary/50 group relative rounded-lg border p-4 transition-colors"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1 space-y-2">
            <div class="flex items-center gap-2">
              <h3 class="text-foreground font-medium tracking-tight">
                {{ task.title }}
              </h3>
              <span
                v-if="task.priority"
                :class="{
                  'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300': task.priority === 'high',
                  'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300':
                    task.priority === 'medium',
                  'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300': task.priority === 'low',
                }"
                class="rounded-full px-2 py-0.5 text-xs font-medium"
              >
                {{ task.priority }}
              </span>
            </div>

            <div class="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
              <span>Deleted {{ formatDate(task.deleted_at) }}</span>
              <span v-if="task.assignee">Assigned to: {{ task.assignee.name }}</span>
              <span v-if="task.project">Project: {{ task.project.name }}</span>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <button
              @click="handleRestore(task)"
              :disabled="restoring"
              class="hover:bg-muted rounded-md p-1.5 transition-colors disabled:cursor-not-allowed disabled:opacity-50"
              title="Restore"
            >
              <Icon name="lucide:undo-2" class="size-4" />
            </button>
            <button
              @click="openForceDeleteDialog(task)"
              :disabled="forceDeleting"
              class="hover:bg-destructive/10 text-destructive rounded-md p-1.5 transition-colors disabled:cursor-not-allowed disabled:opacity-50"
              title="Delete Permanently"
            >
              <Icon name="lucide:trash-2" class="size-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Force Delete Dialog -->
    <DialogResponsive v-model:open="forceDeleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">Permanently Delete Task?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            Are you sure you want to permanently delete <strong>{{ taskToForceDelete?.title }}</strong>?
            This action cannot be undone.
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <button
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              @click="forceDeleteDialogOpen = false"
            >
              Cancel
            </button>
            <button
              @click="handleForceDelete"
              :disabled="forceDeleting"
              class="bg-destructive hover:bg-destructive/90 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Spinner v-if="forceDeleting" class="size-4" />
              <span v-else>Delete Permanently</span>
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import BackButton from "@/components/BackButton.vue";
import DialogResponsive from "@/components/DialogResponsive.vue";

definePageMeta({
  middleware: ['auth', 'verified'],
  layout: 'default',
});

const forceDeleteDialogOpen = ref(false);
const taskToForceDelete = ref(null);
const restoring = ref(false);
const forceDeleting = ref(false);

// Fetch trashed tasks
const { data: tasks, pending, error, refresh } = await useFetch('/api/tasks/trash');
const trashedTasks = computed(() => tasks.value?.data || []);

const formatDate = (date) => {
  if (!date) return '';
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};

const handleRestore = async (task) => {
  restoring.value = true;
  try {
    await $fetch(`/api/tasks/trash/${task.id}/restore`, {
      method: 'POST',
    });

    // Refresh the list
    await refresh();

    // Show success toast (if you have toast component)
    // toast.success('Task restored successfully');
  } catch (error) {
    console.error('Failed to restore task:', error);
    // toast.error('Failed to restore task');
  } finally {
    restoring.value = false;
  }
};

const openForceDeleteDialog = (task) => {
  taskToForceDelete.value = task;
  forceDeleteDialogOpen.value = true;
};

const handleForceDelete = async () => {
  if (!taskToForceDelete.value) return;

  forceDeleting.value = true;
  try {
    await $fetch(`/api/tasks/trash/${taskToForceDelete.value.id}`, {
      method: 'DELETE',
    });

    // Refresh the list
    await refresh();

    // Close dialog
    forceDeleteDialogOpen.value = false;
    taskToForceDelete.value = null;

    // Show success toast (if you have toast component)
    // toast.success('Task permanently deleted');
  } catch (error) {
    console.error('Failed to permanently delete task:', error);
    // toast.error('Failed to permanently delete task');
  } finally {
    forceDeleting.value = false;
  }
};

// Set page meta
useHead({
  title: 'Trash - Tasks',
});
</script>
