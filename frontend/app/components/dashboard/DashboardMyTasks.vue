<script setup lang="ts">
interface TaskItem {
  id: number;
  ulid: string;
  title: string;
  status: string;
  priority: string | null;
  project_name: string | null;
  project_username: string | null;
  due_date: string | null;
  is_overdue: boolean;
}

defineProps<{
  tasks: TaskItem[];
  loading?: boolean;
}>();

const statusDot: Record<string, string> = {
  in_progress: "bg-blue-500",
  todo: "bg-zinc-400 dark:bg-zinc-500",
};

const priorityClass: Record<string, string> = {
  high: "text-red-600 dark:text-red-400",
  medium: "text-amber-600 dark:text-amber-400",
  low: "text-muted-foreground",
};

const formatDue = (dateStr: string | null) => {
  if (!dateStr) return null;
  return new Date(dateStr).toLocaleDateString("id-ID", {
    day: "numeric",
    month: "short",
  });
};
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h3 class="page-title text-lg!">My Tasks</h3>
      <NuxtLink
        to="/projects"
        class="text-muted-foreground hover:text-foreground flex items-center gap-x-1 text-sm tracking-tight"
      >
        <span>View all</span>
        <Icon name="hugeicons:arrow-right-02" class="size-4 shrink-0" />
      </NuxtLink>
    </div>

    <!-- Loading -->
    <template v-if="loading">
      <div class="space-y-3">
        <div v-for="i in 3" :key="i" class="flex items-center gap-2.5">
          <Skeleton class="size-1.5 shrink-0 rounded-full" />
          <Skeleton class="h-3.5 w-3/4" />
        </div>
      </div>
    </template>

    <!-- Empty -->
    <template v-else-if="!tasks || tasks.length === 0">
      <div class="flex items-center gap-2 py-4">
        <Icon name="hugeicons:task-daily-01" class="text-muted-foreground size-4" />
        <p class="text-muted-foreground text-sm tracking-tight">No pending tasks</p>
      </div>
    </template>

    <!-- Task List -->
    <div v-else class="space-y-1">
      <NuxtLink
        v-for="task in tasks"
        :key="task.id"
        :to="task.project_username ? `/projects/${task.project_username}/tasks` : '#'"
        class="flex items-center gap-2.5 transition-opacity hover:opacity-80"
      >
        <!-- Status dot -->
        <span class="size-1.5 shrink-0 rounded-full" :class="statusDot[task.status] || statusDot.todo" />

        <!-- Content -->
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm tracking-tight">
            {{ task.title }}
          </p>
        </div>

        <!-- Meta: project + priority + due -->
        <div class="flex shrink-0 items-center gap-2">
          <span v-if="task.project_name" class="text-muted-foreground hidden text-xs tracking-tight sm:inline">
            {{ task.project_name }}
          </span>
          <span v-if="task.priority" class="text-[10px] font-medium capitalize tracking-tight sm:text-xs" :class="priorityClass[task.priority]">
            {{ task.priority }}
          </span>
          <span
            v-if="task.is_overdue"
            class="rounded bg-red-100 px-1 py-px text-[10px] font-medium leading-none text-red-700 sm:text-xs dark:bg-red-900/30 dark:text-red-400"
          >
            Overdue
          </span>
          <span v-else-if="task.due_date" class="text-muted-foreground text-xs tabular-nums tracking-tight">
            {{ formatDue(task.due_date) }}
          </span>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>
