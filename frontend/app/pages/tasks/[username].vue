<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <!-- Header -->
    <TasksHeader :title="pageTitle" icon="hugeicons:task-01">
      <template #actions>
        <NuxtLink
          to="/tasks"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="lucide:arrow-left" class="size-4 shrink-0" />
          <span>Back to Tasks</span>
        </NuxtLink>
      </template>
    </TasksHeader>

    <!-- Project Info Card -->
    <div v-if="project" class="border-border bg-card rounded-xl border p-4">
      <div class="flex items-center gap-3">
        <NuxtLink
          :to="`/projects/${project.username}`"
          class="bg-muted border-border relative size-16 shrink-0 overflow-hidden rounded-2xl border"
        >
          <NuxtImg
            v-if="project.profile_image?.sm"
            :src="project.profile_image.sm"
            :alt="project.name"
            class="size-full object-contain"
          />
          <div v-else class="flex size-full items-center justify-center">
            <Icon name="lucide:folder" class="text-muted-foreground size-6" />
          </div>
        </NuxtLink>

        <div class="flex flex-col gap-y-1">
          <NuxtLink
            :to="`/projects/${project.username}`"
            class="text-primary text-lg font-semibold hover:underline"
          >
            {{ project.name }}
          </NuxtLink>
          <p v-if="project.bio" class="text-muted-foreground line-clamp-2 text-sm">
            {{ project.bio }}
          </p>
          <div class="flex items-center gap-x-3">
            <NuxtLink
              v-if="project.more_details?.instagram"
              :to="`https://www.instagram.com/${project.more_details.instagram}`"
              target="_blank"
              class="text-muted-foreground hover:text-primary transition"
            >
              <Icon name="hugeicons:instagram" class="size-4" />
            </NuxtLink>
            <NuxtLink
              v-if="project.more_details?.website"
              :to="project.more_details.website"
              target="_blank"
              class="text-muted-foreground hover:text-primary transition"
            >
              <Icon name="hugeicons:globe-02" class="size-4" />
            </NuxtLink>
          </div>
        </div>

        <div class="ml-auto flex items-center gap-2">
          <Badge variant="secondary">
            {{ taskStats.total }} tasks
          </Badge>
        </div>
      </div>
    </div>

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
        {{ searchQuery ? 'Try adjusting your search query.' : 'Create your first task to get started!' }}
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
            @update-status="handleUpdateStatus"
            @delete="openDeleteDialog"
          />
        </div>
      </div>

      <!-- To Do Tasks -->
      <div v-if="todoTasks.length > 0" class="space-y-3">
        <div class="flex items-center gap-2">
          <Icon name="lucide:circle" class="text-muted-foreground size-4" />
          <h2 class="text-sm font-semibold tracking-tight">To Do</h2>
          <Badge variant="secondary" class="text-xs">{{ todoTasks.length }}</Badge>
        </div>
        <div class="space-y-2">
          <TaskCard
            v-for="task in todoTasks"
            :key="task.id"
            :task="task"
            @update-status="handleUpdateStatus"
            @delete="openDeleteDialog"
          />
        </div>
      </div>

      <!-- Completed Tasks -->
      <div v-if="completedTasks.length > 0" class="space-y-3">
        <div class="flex items-center gap-2">
          <Icon name="lucide:check" class="size-4 text-green-600 dark:text-green-500" />
          <h2 class="text-sm font-semibold tracking-tight">Completed</h2>
          <Badge variant="secondary" class="text-xs">{{ completedTasks.length }}</Badge>
        </div>
        <div class="space-y-2">
          <TaskCard
            v-for="task in completedTasks"
            :key="task.id"
            :task="task"
            @update-status="handleUpdateStatus"
            @delete="openDeleteDialog"
          />
        </div>
      </div>

      <!-- Archived Tasks -->
      <div v-if="archivedTasks.length > 0" class="space-y-3">
        <div class="flex items-center gap-2">
          <Icon name="lucide:archive" class="text-muted-foreground size-4" />
          <h2 class="text-sm font-semibold tracking-tight">Archived</h2>
          <Badge variant="secondary" class="text-xs">{{ archivedTasks.length }}</Badge>
        </div>
        <div class="space-y-2">
          <TaskCard
            v-for="task in archivedTasks"
            :key="task.id"
            :task="task"
            @update-status="handleUpdateStatus"
            @delete="openDeleteDialog"
          />
        </div>
      </div>
    </div>

    <!-- Delete Dialog -->
    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-foreground text-lg font-semibold tracking-tight">Delete Task?</div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            Are you sure you want to delete <strong>{{ taskToDelete?.title }}</strong>? This action can be undone from trash.
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <Button variant="outline" @click="deleteDialogOpen = false">
              Cancel
            </Button>
            <Button
              variant="destructive"
              @click="handleDeleteTask"
              :disabled="deleteLoading"
            >
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
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import DialogResponsive from "@/components/DialogResponsive.vue";
import TasksHeader from "@/components/task/TasksHeader.vue";
import TasksFilters from "@/components/task/TasksFilters.vue";
import TaskCard from "@/components/task/TaskCard.vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const route = useRoute();
const username = route.params.username;

// Filter state
const searchQuery = ref("");
const selectedStatuses = ref([]);
const selectedPriorities = ref([]);

// Fetch project info
const { data: projectResponse } = await useLazySanctumFetch(`/api/projects/${username}`, {
  key: `project-${username}`,
});
const project = computed(() => projectResponse.value?.data);

// Page title
const pageTitle = computed(() => {
  return project.value ? `${project.value.name} Tasks` : 'Project Tasks';
});

// Fetch tasks for this project
const {
  data: tasksResponse,
  pending,
  error,
  refresh,
} = await useLazySanctumFetch('/api/tasks', {
  query: {
    per_page: 100,
    filter_project: computed(() => project.value?.id),
  },
  key: `tasks-project-${username}`,
  watch: [() => project.value?.id],
});

const allTasks = computed(() => tasksResponse.value?.data || []);

// Filter tasks
const filteredTasks = computed(() => {
  let filtered = allTasks.value;

  // Search filter
  if (searchQuery.value) {
    const search = searchQuery.value.toLowerCase();
    filtered = filtered.filter((task) =>
      task.title.toLowerCase().includes(search) ||
      task.description?.toLowerCase().includes(search)
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

// Group tasks by status
const inProgressTasks = computed(() => filteredTasks.value.filter((t) => t.status === 'in_progress'));
const todoTasks = computed(() => filteredTasks.value.filter((t) => t.status === 'todo'));
const completedTasks = computed(() => filteredTasks.value.filter((t) => t.status === 'completed'));
const archivedTasks = computed(() => filteredTasks.value.filter((t) => t.status === 'archived'));

// Task stats
const taskStats = computed(() => ({
  total: allTasks.value.length,
  inProgress: allTasks.value.filter((t) => t.status === 'in_progress').length,
  todo: allTasks.value.filter((t) => t.status === 'todo').length,
  completed: allTasks.value.filter((t) => t.status === 'completed').length,
}));

// Update task status
const handleUpdateStatus = async (task, newStatus) => {
  try {
    const client = useSanctumClient();
    await client(`/api/tasks/${task.ulid}`, {
      method: 'PUT',
      body: { status: newStatus },
    });

    await refresh();
    toast.success(`Task marked as ${newStatus.replace('_', ' ')}`);
  } catch (err) {
    console.error('Failed to update task status:', err);
    toast.error('Failed to update task status');
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
      method: 'DELETE',
    });

    await refresh();
    deleteDialogOpen.value = false;
    taskToDelete.value = null;
    toast.success('Task deleted successfully');
  } catch (err) {
    console.error('Failed to delete task:', err);
    toast.error('Failed to delete task');
  } finally {
    deleteLoading.value = false;
  }
};

// Set page meta
useHead({
  title: pageTitle,
});
</script>
