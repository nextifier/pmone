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
      <Button variant="outline" size="sm" class="mt-4" @click="refresh">
        <Icon name="lucide:refresh-cw" class="size-4" />
        <span>Try Again</span>
      </Button>
    </div>

    <div
      v-else-if="!trashedTasks || trashedTasks.length === 0"
      class="border-border bg-card rounded-lg border p-12 text-center"
    >
      <Icon name="lucide:trash-2" class="text-muted-foreground mx-auto mb-3 size-12" />
      <p class="text-muted-foreground mb-4 text-sm">No deleted tasks found.</p>
      <Button variant="outline" size="sm" as-child>
        <NuxtLink to="/tasks">
          <Icon name="lucide:arrow-left" class="size-4" />
          <span>Back to Tasks</span>
        </NuxtLink>
      </Button>
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="task in trashedTasks"
        :key="task.id"
        class="border-border bg-card hover:border-primary/50 group relative rounded-lg border p-4 transition-colors"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="text-foreground font-medium tracking-tight">
                {{ task.title }}
              </h3>
              <Badge
                v-if="task.priority"
                :class="priorityBadgeClass(task.priority)"
                variant="outline"
              >
                {{ task.priority }}
              </Badge>
            </div>

            <div class="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
              <span class="flex items-center gap-1">
                <Icon name="lucide:trash" class="size-3" />
                Deleted {{ formatDate(task.deleted_at) }}
              </span>
              <span v-if="task.assignee" class="flex items-center gap-1">
                <Icon name="lucide:user" class="size-3" />
                {{ task.assignee.name }}
              </span>
              <span v-if="task.project" class="flex items-center gap-1">
                <Icon name="lucide:folder" class="size-3" />
                {{ task.project.name }}
              </span>
            </div>
          </div>

          <div class="flex items-center gap-1">
            <Button
              variant="ghost"
              size="icon"
              class="size-8"
              @click="handleRestore(task)"
              :disabled="restoring"
              title="Restore"
            >
              <Icon name="lucide:undo-2" class="size-4" />
            </Button>
            <Button
              variant="ghost"
              size="icon"
              class="text-destructive hover:bg-destructive/10 hover:text-destructive size-8"
              @click="openForceDeleteDialog(task)"
              :disabled="forceDeleting"
              title="Delete Permanently"
            >
              <Icon name="lucide:trash-2" class="size-4" />
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Force Delete Dialog -->
    <DialogResponsive v-model:open="forceDeleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-foreground text-lg font-semibold tracking-tight">Permanently Delete Task?</div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            Are you sure you want to permanently delete <strong>{{ taskToForceDelete?.title }}</strong>?
            This action cannot be undone.
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <Button variant="outline" @click="forceDeleteDialogOpen = false">
              Cancel
            </Button>
            <Button
              variant="destructive"
              @click="handleForceDelete"
              :disabled="forceDeleting"
            >
              <Spinner v-if="forceDeleting" class="size-4" />
              <span v-else>Delete Permanently</span>
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import BackButton from "@/components/BackButton.vue";
import DialogResponsive from "@/components/DialogResponsive.vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const forceDeleteDialogOpen = ref(false);
const taskToForceDelete = ref(null);
const restoring = ref(false);
const forceDeleting = ref(false);

// Fetch trashed tasks
const { data: tasks, pending, error, refresh } = await useLazySanctumFetch('/api/tasks/trash', {
  key: 'tasks-trash',
});
const trashedTasks = computed(() => tasks.value?.data || []);

const formatDate = (date) => {
  if (!date) return '';
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};

// Badge class helpers
const priorityBadgeClass = (priority) => {
  const classes = {
    high: 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-400',
    medium: 'border-yellow-200 bg-yellow-50 text-yellow-700 dark:border-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    low: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
  };
  return classes[priority] || '';
};

const handleRestore = async (task) => {
  restoring.value = true;
  try {
    const client = useSanctumClient();
    await client(`/api/tasks/trash/${task.id}/restore`, {
      method: 'POST',
    });

    // Refresh the list
    await refresh();

    toast.success('Task restored successfully');
  } catch (err) {
    console.error('Failed to restore task:', err);
    toast.error('Failed to restore task');
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
    const client = useSanctumClient();
    await client(`/api/tasks/trash/${taskToForceDelete.value.id}`, {
      method: 'DELETE',
    });

    // Refresh the list
    await refresh();

    // Close dialog
    forceDeleteDialogOpen.value = false;
    taskToForceDelete.value = null;

    toast.success('Task permanently deleted');
  } catch (err) {
    console.error('Failed to permanently delete task:', err);
    toast.error('Failed to permanently delete task');
  } finally {
    forceDeleting.value = false;
  }
};

// Set page meta
useHead({
  title: 'Trash - Tasks',
});
</script>
