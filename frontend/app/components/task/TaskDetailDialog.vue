<template>
  <div class="px-4 pb-10 md:px-6 md:py-5">
    <!-- Status Badge + Overdue -->
    <div class="flex items-center gap-x-2">
      <div
        class="flex items-center gap-x-1.5 rounded-full px-2.5 py-1"
        :class="statusBadgeClass(task.status)"
      >
        <Icon :name="statusIcon(task.status)" class="size-3.5" />
        <span class="text-xs font-medium tracking-tight capitalize sm:text-sm">
          {{ formatStatus(task.status) }}
        </span>
      </div>
      <Badge v-if="task.is_overdue" variant="destructive" class="text-xs sm:text-sm">
        Overdue
      </Badge>
    </div>

    <!-- Title -->
    <h2
      class="mt-3 text-lg leading-snug font-semibold tracking-tight"
      :class="task.status === 'completed' ? 'text-muted-foreground line-through' : ''"
    >
      {{ task.title }}
    </h2>

    <!-- Priority / Complexity / Visibility -->
    <div
      v-if="task.priority || task.complexity || task.visibility"
      class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2"
    >
      <PriorityBars v-if="task.priority" :level="task.priority" label="Priority" />
      <PriorityBars v-if="task.complexity" :level="task.complexity" label="Complexity" />
      <span
        v-if="task.visibility"
        class="text-muted-foreground flex items-center gap-x-1 text-xs tracking-tight sm:text-sm"
      >
        <Icon :name="visibilityIcon(task.visibility)" class="size-3.5" />
        <span class="capitalize">{{ task.visibility }}</span>
      </span>
    </div>

    <!-- Description -->
    <div v-if="task.description" class="border-border mt-5 border-t pt-2">
      <div class="task-description format-html" v-html="task.description"></div>
    </div>

    <!-- Info Grid -->
    <div class="border-border mt-5 grid grid-cols-1 gap-3 border-t pt-5 sm:grid-cols-2">
      <!-- Assignee -->
      <div v-if="task.assignee" class="flex items-center gap-x-3">
        <Avatar :model="task.assignee" size="sm" class="size-8 shrink-0" />
        <div class="min-w-0">
          <div class="text-muted-foreground text-xs tracking-tight sm:text-sm">Assigned To</div>
          <div class="truncate text-xs font-medium tracking-tight sm:text-sm">
            {{ task.assignee.name }}
          </div>
        </div>
      </div>

      <!-- Project -->
      <div v-if="task.project" class="flex items-center gap-x-3">
        <Avatar :model="task.project" size="sm" class="size-8 shrink-0" rounded="rounded-md" />
        <div class="min-w-0">
          <div class="text-muted-foreground text-xs tracking-tight sm:text-sm">Project</div>
          <div class="truncate text-xs font-medium tracking-tight sm:text-sm">
            {{ task.project.name }}
          </div>
        </div>
      </div>

      <!-- Estimated Start -->
      <div v-if="task.estimated_start_at" class="flex items-center gap-x-3">
        <div class="bg-muted flex size-8 shrink-0 items-center justify-center rounded-full">
          <Icon name="hugeicons:calendar-03" class="text-muted-foreground size-4" />
        </div>
        <div class="min-w-0">
          <div class="text-muted-foreground text-xs tracking-tight sm:text-sm">Start Date</div>
          <div class="text-xs font-medium tracking-tight sm:text-sm">
            {{ formatDateTime(task.estimated_start_at) }}
          </div>
        </div>
      </div>

      <!-- Estimated Completion -->
      <div v-if="task.estimated_completion_at" class="flex items-center gap-x-3">
        <div
          class="flex size-8 shrink-0 items-center justify-center rounded-full"
          :class="task.is_overdue ? 'bg-destructive/10' : 'bg-muted'"
        >
          <Icon
            name="hugeicons:time-schedule"
            class="size-4"
            :class="task.is_overdue ? 'text-destructive' : 'text-muted-foreground'"
          />
        </div>
        <div class="min-w-0">
          <div class="text-muted-foreground text-xs tracking-tight sm:text-sm">Due Date</div>
          <div
            class="text-xs font-medium tracking-tight sm:text-sm"
            :class="task.is_overdue ? 'text-destructive' : ''"
          >
            {{ formatDateTime(task.estimated_completion_at) }}
          </div>
        </div>
      </div>

      <!-- Completed At -->
      <div v-if="task.completed_at" class="flex items-center gap-x-3">
        <div
          class="bg-success-foreground/10 flex size-8 shrink-0 items-center justify-center rounded-full"
        >
          <Icon name="hugeicons:checkmark-circle-02" class="text-success-foreground size-4" />
        </div>
        <div class="min-w-0">
          <div class="text-muted-foreground text-xs tracking-tight sm:text-sm">Completed</div>
          <div class="text-xs font-medium tracking-tight sm:text-sm">
            {{ formatDateTime(task.completed_at) }}
          </div>
        </div>
      </div>
    </div>

    <!-- Shared Users -->
    <div
      v-if="task.shared_users && task.shared_users.length > 0"
      class="border-border mt-5 border-t pt-5"
    >
      <div class="text-muted-foreground mb-2 text-xs tracking-tight sm:text-sm">Shared With</div>
      <div class="flex flex-wrap gap-2">
        <div
          v-for="sharedUser in task.shared_users"
          :key="sharedUser.id"
          class="border-border bg-muted/50 flex items-center gap-x-2 rounded-full border px-2.5 py-1"
        >
          <Avatar :model="sharedUser" size="sm" class="size-5" />
          <span class="text-xs font-medium tracking-tight sm:text-sm">{{ sharedUser.name }}</span>
          <Badge variant="secondary" class="h-4 px-1.5 text-[10px]">{{ sharedUser.role }}</Badge>
        </div>
      </div>
    </div>

    <!-- Metadata Footer -->
    <div class="border-border mt-5 border-t pt-4">
      <div
        class="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-3 text-xs tracking-tight sm:text-sm"
      >
        <span v-if="task.creator" class="flex items-center gap-x-1">
          <Icon name="hugeicons:user" class="size-3.5" />
          {{ task.creator.name }}
        </span>
        <span v-if="task.created_at" class="flex items-center gap-x-1">
          <Icon name="hugeicons:calendar-add-01" class="size-3.5" />
          {{ formatDateTime(task.created_at) }}
        </span>
        <span
          v-if="task.updated_at && task.updated_at !== task.created_at"
          class="flex items-center gap-x-1"
        >
          <Icon name="hugeicons:clock-01" class="size-3.5" />
          Updated {{ formatDateTime(task.updated_at) }}
        </span>
      </div>
    </div>

    <!-- Actions -->
    <div class="mt-5 flex items-center justify-end gap-2">
      <Button variant="outline" size="sm" @click="$emit('close')">Close</Button>
      <Button v-if="canEdit" size="sm" @click="$emit('edit', task)">
        <Icon name="hugeicons:pencil-edit-01" class="size-4" />
        <span>Edit</span>
      </Button>
    </div>
  </div>
</template>

<script setup>
import Avatar from "@/components/Avatar.vue";
import PriorityBars from "@/components/task/PriorityBars.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";

defineProps({
  task: {
    type: Object,
    required: true,
  },
  canEdit: {
    type: Boolean,
    default: false,
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

const statusIcon = (status) => {
  const icons = {
    todo: "hugeicons:task-daily-01",
    in_progress: "hugeicons:loading-03",
    completed: "hugeicons:checkmark-circle-02",
    archived: "hugeicons:archive-02",
  };
  return icons[status] || "hugeicons:task-daily-01";
};

const statusBadgeClass = (status) => {
  const classes = {
    todo: "bg-muted text-muted-foreground",
    in_progress: "bg-info-foreground/10 text-info-foreground",
    completed: "bg-success-foreground/10 text-success-foreground",
    archived: "bg-muted text-muted-foreground",
  };
  return classes[status] || "bg-muted text-muted-foreground";
};

const formatStatus = (status) => {
  return status.replace("_", " ");
};

const visibilityIcon = (visibility) => {
  const icons = {
    public: "hugeicons:globe-02",
    private: "hugeicons:lock",
    shared: "hugeicons:users",
  };
  return icons[visibility] || "hugeicons:view";
};
</script>

<style scoped>
@reference "../../assets/css/main.css";

:deep(.task-description p) {
  @apply my-2;
}

:deep(.task-description ul),
:deep(.task-description ol) {
  @apply my-2;
}

:deep(.task-description li) {
  @apply my-0.5;
}
</style>
