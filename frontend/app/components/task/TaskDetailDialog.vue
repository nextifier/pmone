<template>
  <div class="space-y-5 px-4 pb-10 md:px-6 md:py-5">
    <!-- Header -->
    <div class="space-y-3">
      <div class="flex flex-wrap items-start gap-2">
        <h2 class="text-lg font-semibold tracking-tight">{{ task.title }}</h2>
        <Badge
          v-if="task.priority"
          :class="priorityBadgeClass(task.priority)"
          variant="outline"
          class="shrink-0"
        >
          {{ task.priority }}
        </Badge>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <Badge :class="statusBadgeClass(task.status)" variant="outline">
          <Icon :name="statusIcon(task.status)" class="size-3" />
          {{ formatStatus(task.status) }}
        </Badge>
        <Badge v-if="task.complexity" variant="secondary">
          <Icon name="hugeicons:dashboard-speed-01" class="size-3" />
          {{ task.complexity }}
        </Badge>
        <Badge v-if="task.visibility" variant="outline">
          <Icon :name="visibilityIcon(task.visibility)" class="size-3" />
          {{ task.visibility }}
        </Badge>
      </div>
    </div>

    <!-- Description -->
    <div v-if="task.description" class="space-y-2">
      <Label class="text-foreground text-sm font-semibold">Description</Label>
      <div
        class="prose dark:prose-invert text-muted-foreground max-w-none text-sm"
        v-html="task.description"
      ></div>
    </div>

    <!-- Task Info Grid -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <!-- Assignee -->
      <div v-if="task.assignee" class="space-y-1.5">
        <Label class="text-foreground text-xs font-semibold">Assigned To</Label>
        <div class="flex items-center gap-2">
          <Avatar :model="task.assignee" size="sm" class="size-6" />
          <span class="text-muted-foreground text-sm">{{ task.assignee.name }}</span>
        </div>
      </div>

      <!-- Project -->
      <div v-if="task.project" class="space-y-1.5">
        <Label class="text-foreground text-xs font-semibold">Project</Label>
        <div class="flex items-center gap-2">
          <Avatar :model="task.project" size="sm" class="size-6" rounded="rounded" />
          <span class="text-muted-foreground text-sm">{{ task.project.name }}</span>
        </div>
      </div>

      <!-- Estimated Start -->
      <div v-if="task.estimated_start_at" class="space-y-1.5">
        <Label class="text-foreground text-xs font-semibold">Estimated Start</Label>
        <p class="text-muted-foreground flex items-center gap-1.5 text-sm">
          <Icon name="hugeicons:calendar-01" class="size-4" />
          {{ formatDateTime(task.estimated_start_at) }}
        </p>
      </div>

      <!-- Estimated Completion -->
      <div v-if="task.estimated_completion_at" class="space-y-1.5">
        <Label class="text-foreground text-xs font-semibold">Estimated Completion</Label>
        <p
          :class="task.is_overdue ? 'text-destructive' : 'text-muted-foreground'"
          class="flex items-center gap-1.5 text-sm"
        >
          <Icon name="hugeicons:time-schedule" class="size-4" />
          {{ formatDateTime(task.estimated_completion_at) }}
          <Badge v-if="task.is_overdue" variant="destructive" class="ml-1 text-xs">Overdue</Badge>
        </p>
      </div>

      <!-- Completed At -->
      <div v-if="task.completed_at" class="space-y-1.5">
        <Label class="text-foreground text-xs font-semibold">Completed At</Label>
        <p class="text-muted-foreground flex items-center gap-1.5 text-sm">
          <Icon name="hugeicons:checkmark-circle-02" class="size-4 text-green-600" />
          {{ formatDateTime(task.completed_at) }}
        </p>
      </div>
    </div>

    <!-- Shared Users -->
    <div v-if="task.shared_users && task.shared_users.length > 0" class="space-y-2">
      <Label class="text-foreground text-xs font-semibold">Shared With</Label>
      <div class="flex flex-wrap gap-2">
        <div
          v-for="sharedUser in task.shared_users"
          :key="sharedUser.id"
          class="border-border bg-muted flex items-center gap-2 rounded-full border px-2.5 py-1"
        >
          <Avatar :model="sharedUser" size="sm" class="size-5" />
          <span class="text-xs">{{ sharedUser.name }}</span>
          <Badge variant="secondary" class="text-[10px]">{{ sharedUser.role }}</Badge>
        </div>
      </div>
    </div>

    <!-- Metadata -->
    <div class="border-border space-y-2 border-t pt-4">
      <div class="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
        <span v-if="task.creator" class="flex items-center gap-1">
          <Icon name="hugeicons:user" class="size-3" />
          Created by {{ task.creator.name }}
        </span>
        <span v-if="task.created_at" class="flex items-center gap-1">
          <Icon name="hugeicons:calendar-add-01" class="size-3" />
          {{ formatDateTime(task.created_at) }}
        </span>
        <span v-if="task.updated_at" class="flex items-center gap-1">
          <Icon name="hugeicons:clock-01" class="size-3" />
          Updated {{ formatDateTime(task.updated_at) }}
        </span>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-end gap-2 pt-2">
      <Button variant="outline" size="sm" @click="$emit('close')">Close</Button>
      <Button size="sm" @click="$emit('edit', task)">
        <Icon name="hugeicons:pencil-edit-01" class="size-4" />
        <span>Edit</span>
      </Button>
    </div>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import Avatar from "@/components/Avatar.vue";

defineProps({
  task: {
    type: Object,
    required: true,
  },
});

defineEmits(["close", "edit"]);

const formatDateTime = (date) => {
  if (!date) return "";
  return new Date(date).toLocaleString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
};

const priorityBadgeClass = (priority) => {
  const classes = {
    high: "border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-400",
    medium:
      "border-yellow-200 bg-yellow-50 text-yellow-700 dark:border-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400",
    low: "border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-400",
  };
  return classes[priority] || "";
};

const statusBadgeClass = (status) => {
  const classes = {
    todo: "border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-400",
    in_progress:
      "border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-400",
    completed:
      "border-green-200 bg-green-50 text-green-700 dark:border-green-800 dark:bg-green-900/30 dark:text-green-400",
    archived:
      "border-gray-200 bg-gray-100 text-gray-500 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-500",
  };
  return classes[status] || "";
};

const statusIcon = (status) => {
  const icons = {
    todo: "hugeicons:task-01",
    in_progress: "hugeicons:loading-03",
    completed: "hugeicons:checkmark-circle-02",
    archived: "hugeicons:archive-02",
  };
  return icons[status] || "hugeicons:task-01";
};

const visibilityIcon = (visibility) => {
  const icons = {
    public: "hugeicons:globe-02",
    private: "hugeicons:lock",
    shared: "hugeicons:users",
  };
  return icons[visibility] || "hugeicons:view";
};

const formatStatus = (status) => {
  return status.replace("_", " ");
};
</script>
