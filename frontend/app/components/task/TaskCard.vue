<template>
  <div class="border-border bg-card group flex items-start gap-x-3 rounded-lg border p-3">
    <!-- Status Icon -->
    <!-- <div class="shrink-0 pt-0.5">
      <Icon
        v-if="task.status === 'completed'"
        name="hugeicons:checkmark-circle-02"
        class="size-5 text-green-600 dark:text-green-500"
      />
      <TaskLoaderBars v-else-if="task.status === 'in_progress'" />
      <Icon
        v-else-if="task.status === 'todo'"
        name="hugeicons:task-01"
        class="text-muted-foreground size-5"
      />
      <Icon v-else name="hugeicons:archive-02" class="text-muted-foreground size-5" />
    </div> -->

    <!-- Task Content -->
    <div class="flex min-w-0 flex-1 flex-col gap-y-1.5">
      <button
        type="button"
        @click="$emit('view', task)"
        class="text-primary hover:text-primary/80 line-clamp-2 cursor-pointer text-left text-sm font-medium tracking-tight hover:underline"
      >
        {{ task.title }}
      </button>

      <!-- Description -->
      <p v-if="task.description" class="text-muted-foreground line-clamp-2 text-xs">
        {{ stripHtml(task.description) }}
      </p>

      <!-- Meta Info -->
      <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
        <!-- Priority & Complexity -->
        <div v-if="task.priority || task.complexity" class="flex items-center gap-x-3">
          <TaskPriorityBars v-if="task.priority" :level="task.priority" label="Priority" />
          <span v-if="task.priority && task.complexity" class="bg-primary/20 h-3 w-px"></span>
          <TaskPriorityBars v-if="task.complexity" :level="task.complexity" label="Complexity" />
        </div>

        <!-- Date Range -->
        <div
          v-if="task.estimated_start_at || task.estimated_completion_at"
          class="flex items-center gap-x-1.5 text-xs"
        >
          <Icon name="hugeicons:calendar-01" class="text-muted-foreground size-3" />
          <span v-if="task.estimated_start_at" class="text-muted-foreground">
            {{ formatDateShort(task.estimated_start_at) }}
          </span>
          <span
            v-if="task.estimated_start_at && task.estimated_completion_at"
            class="text-muted-foreground"
            >→</span
          >
          <span v-if="task.estimated_completion_at" class="text-muted-foreground">
            {{ formatDateShort(task.estimated_completion_at) }}
          </span>
        </div>

        <!-- Status Text -->
        <span
          v-if="task.status === 'completed' && task.completed_at"
          class="text-xs text-green-700 dark:text-green-500"
        >
          Completed {{ formatRelativeTime(task.completed_at) }}
        </span>
        <span
          v-else-if="task.status === 'in_progress'"
          class="bg-gradient-to-r from-indigo-400 via-sky-500 to-emerald-500 bg-clip-text text-xs font-semibold text-transparent"
        >
          Currently in progress
        </span>
        <span
          v-else-if="task.status === 'todo' && task.estimated_start_at"
          class="text-xs text-amber-700 dark:text-amber-500"
        >
          Starts {{ formatRelativeTime(task.estimated_start_at) }}
        </span>
      </div>

      <!-- Assignee -->
      <div v-if="task.assignee" class="flex items-center gap-1.5 pt-1">
        <Avatar :model="task.assignee" size="sm" class="size-5" />
        <span class="text-muted-foreground text-xs">{{ task.assignee.name }}</span>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex shrink-0 items-center gap-1">
      <!-- Mark as Completed -->
      <Button
        v-if="task.status !== 'completed'"
        v-tippy="'Mark as completed'"
        variant="ghost"
        size="icon"
        class="size-8 text-green-600 hover:bg-green-100 hover:text-green-700 dark:text-green-500 dark:hover:bg-green-900/30"
        @click="$emit('updateStatus', task, 'completed')"
      >
        <Icon name="hugeicons:tick-02" class="size-4" />
      </Button>

      <!-- Reopen / Stop Progress -->
      <Button
        v-if="task.status !== 'todo'"
        v-tippy="task.status === 'completed' ? 'Reopen task' : 'Stop progress'"
        variant="ghost"
        size="icon"
        class="text-muted-foreground hover:bg-muted size-8"
        @click="$emit('updateStatus', task, 'todo')"
      >
        <Icon name="hugeicons:rotate-01" class="size-4" />
      </Button>

      <!-- Set as In Progress -->
      <Button
        v-if="task.status !== 'in_progress'"
        v-tippy="'Start working'"
        variant="ghost"
        size="icon"
        class="size-8 text-blue-600 hover:bg-blue-100 hover:text-blue-700 dark:text-blue-500 dark:hover:bg-blue-900/30"
        @click="$emit('updateStatus', task, 'in_progress')"
      >
        <Icon name="hugeicons:play" class="size-4" />
      </Button>

      <!-- Edit -->
      <Button
        v-tippy="'Edit task'"
        variant="ghost"
        size="icon"
        class="size-8"
        @click="$emit('edit', task)"
      >
        <Icon name="hugeicons:pencil-edit-01" class="size-4" />
      </Button>

      <!-- Delete -->
      <Button
        v-tippy="'Delete task'"
        variant="ghost"
        size="icon"
        class="text-destructive hover:bg-destructive/10 hover:text-destructive size-8"
        @click="$emit('delete', task)"
      >
        <Icon name="hugeicons:delete-01" class="size-4" />
      </Button>
    </div>
  </div>
</template>

<script setup>
import Avatar from "@/components/Avatar.vue";
import { Button } from "@/components/ui/button";

defineProps({
  task: {
    type: Object,
    required: true,
  },
});

defineEmits(["updateStatus", "delete", "view", "edit"]);

const stripHtml = (html) => {
  if (!html) return "";
  return html.replace(/<[^>]*>/g, "").substring(0, 150);
};

const formatDateShort = (date) => {
  if (!date) return "";
  return new Date(date).toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
  });
};

const formatRelativeTime = (date) => {
  if (!date) return "";
  const now = new Date();
  const target = new Date(date);
  const diffMs = now - target;
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

  if (diffDays === 0) {
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
    if (diffHours === 0) {
      const diffMinutes = Math.floor(diffMs / (1000 * 60));
      if (diffMinutes < 0) return `in ${Math.abs(diffMinutes)} minutes`;
      if (diffMinutes === 0) return "just now";
      return `${diffMinutes} minutes ago`;
    }
    if (diffHours < 0) return `in ${Math.abs(diffHours)} hours`;
    return `${diffHours} hours ago`;
  }

  if (diffDays < 0) return `in ${Math.abs(diffDays)} days`;
  if (diffDays === 1) return "yesterday";
  if (diffDays < 7) return `${diffDays} days ago`;
  if (diffDays < 30) return `${Math.floor(diffDays / 7)} weeks ago`;
  if (diffDays < 365) return `${Math.floor(diffDays / 30)} months ago`;
  return `${Math.floor(diffDays / 365)} years ago`;
};
</script>
