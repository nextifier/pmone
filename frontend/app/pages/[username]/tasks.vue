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
    <div v-if="pending && !response?.data" class="flex justify-center py-12">
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
              <div class="space-y-4">
                <TaskCard
                  v-for="task in inProgressTasks"
                  :key="task.id"
                  :task="task"
                  :show-details="true"
                  :can-edit="task.can_edit === true"
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
              <div class="space-y-4">
                <TaskCard
                  v-for="task in todoTasks"
                  :key="task.id"
                  :task="task"
                  :show-details="true"
                  :can-edit="task.can_edit === true"
                  @update-status="handleUpdateStatus"
                  @update-title="handleUpdateTitle"
                  @delete="dialogs.openDeleteDialog"
                  @view="dialogs.openDetailDialog"
                  @edit="dialogs.openEditDialog"
                />
              </div>
            </div>

            <!-- Empty pending -->
            <div
              v-if="inProgressTasks.length === 0 && todoTasks.length === 0"
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
              </div>
              <div v-if="completedTasks.length > 0" class="space-y-4">
                <TaskCard
                  v-for="task in completedTasks"
                  :key="task.id"
                  :task="task"
                  :show-details="true"
                  :can-edit="task.can_edit === true"
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
                <span class="text-muted-foreground text-sm tracking-tight"
                  >No completed tasks</span
                >
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Task Dialogs -->
    <TaskDialogs :dialogs="dialogs" />
  </div>
</template>

<script setup>
import Avatar from "@/components/Avatar.vue";
import TaskCard from "@/components/task/TaskCard.vue";
import TaskDialogs from "@/components/task/TaskDialogs.vue";
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

// Task dialogs
const dialogs = useTaskDialogs({ refresh });

usePageMeta(null, {
  title: pageTitle,
});
</script>
