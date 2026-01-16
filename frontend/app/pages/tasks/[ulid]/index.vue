<template>
  <div class="mx-auto max-w-4xl space-y-6 pt-4 pb-16">
    <div v-if="pending" class="flex justify-center py-12">
      <Spinner class="size-8" />
    </div>

    <div v-else-if="error" class="border-border bg-card rounded-lg border p-12 text-center">
      <Icon name="lucide:alert-circle" class="text-destructive mx-auto mb-3 size-12" />
      <p class="text-muted-foreground text-sm">Failed to load task. Please try again.</p>
      <Button variant="outline" size="sm" class="mt-4" @click="$router.back()">
        <Icon name="lucide:arrow-left" class="size-4" />
        <span>Go Back</span>
      </Button>
    </div>

    <div v-else-if="task" class="space-y-6">
      <!-- Header -->
      <div class="flex items-start justify-between gap-4">
        <div class="flex-1 space-y-3">
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-bold tracking-tight">{{ task.title }}</h1>
            <Badge
              v-if="task.priority"
              :class="priorityBadgeClass(task.priority)"
              variant="outline"
            >
              {{ task.priority }} priority
            </Badge>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <Badge :class="statusBadgeClass(task.status)" variant="outline">
              <Icon :name="statusIcon(task.status)" class="size-3" />
              {{ formatStatus(task.status) }}
            </Badge>
            <Badge v-if="task.complexity" variant="secondary">
              <Icon name="lucide:gauge" class="size-3" />
              {{ task.complexity }} complexity
            </Badge>
            <Badge v-if="task.visibility" variant="outline">
              <Icon :name="visibilityIcon(task.visibility)" class="size-3" />
              {{ task.visibility }}
            </Badge>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <BackButton />
          <Button size="sm" as-child>
            <NuxtLink :to="`/tasks/${task.ulid}/edit`">
              <Icon name="lucide:pencil" class="size-4" />
              <span>Edit</span>
            </NuxtLink>
          </Button>
        </div>
      </div>

      <!-- Task Details Card -->
      <div class="border-border bg-card space-y-6 rounded-lg border p-6">
        <!-- Description -->
        <div v-if="task.description">
          <Label class="text-foreground mb-2 block text-sm font-semibold">Description</Label>
          <div class="prose dark:prose-invert text-muted-foreground max-w-none text-sm" v-html="task.description"></div>
        </div>

        <!-- Task Info Grid -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <!-- Assignee -->
          <div v-if="task.assignee">
            <Label class="text-foreground mb-2 block text-sm font-semibold">Assigned To</Label>
            <div class="flex items-center gap-2">
              <Avatar :user="task.assignee" size="sm" />
              <span class="text-muted-foreground text-sm">{{ task.assignee.name }}</span>
            </div>
          </div>

          <!-- Project -->
          <div v-if="task.project">
            <Label class="text-foreground mb-2 block text-sm font-semibold">Project</Label>
            <NuxtLink
              :to="`/projects/${task.project.username}`"
              class="text-primary hover:underline text-sm font-medium"
            >
              {{ task.project.name }}
            </NuxtLink>
          </div>

          <!-- Estimated Start -->
          <div v-if="task.estimated_start_at">
            <Label class="text-foreground mb-2 block text-sm font-semibold">Estimated Start</Label>
            <p class="text-muted-foreground flex items-center gap-1.5 text-sm">
              <Icon name="lucide:calendar" class="size-4" />
              {{ formatDateTime(task.estimated_start_at) }}
            </p>
          </div>

          <!-- Estimated Completion -->
          <div v-if="task.estimated_completion_at">
            <Label class="text-foreground mb-2 block text-sm font-semibold">Estimated Completion</Label>
            <p :class="task.is_overdue ? 'text-destructive' : 'text-muted-foreground'" class="flex items-center gap-1.5 text-sm">
              <Icon name="lucide:calendar-clock" class="size-4" />
              {{ formatDateTime(task.estimated_completion_at) }}
              <Badge v-if="task.is_overdue" variant="destructive" class="ml-1">Overdue</Badge>
            </p>
          </div>

          <!-- Completed At -->
          <div v-if="task.completed_at">
            <Label class="text-foreground mb-2 block text-sm font-semibold">Completed At</Label>
            <p class="text-muted-foreground flex items-center gap-1.5 text-sm">
              <Icon name="lucide:check-circle" class="size-4 text-green-600" />
              {{ formatDateTime(task.completed_at) }}
            </p>
          </div>
        </div>

        <!-- Shared Users -->
        <div v-if="task.shared_users && task.shared_users.length > 0">
          <Label class="text-foreground mb-3 block text-sm font-semibold">Shared With</Label>
          <div class="flex flex-wrap gap-2">
            <div
              v-for="sharedUser in task.shared_users"
              :key="sharedUser.id"
              class="border-border bg-muted flex items-center gap-2 rounded-full border px-3 py-1.5"
            >
              <Avatar :user="sharedUser" size="xs" />
              <span class="text-sm">{{ sharedUser.name }}</span>
              <Badge variant="secondary" class="text-xs">{{ sharedUser.role }}</Badge>
            </div>
          </div>
        </div>

        <!-- Metadata -->
        <div class="border-border border-t pt-4">
          <div class="text-muted-foreground flex flex-wrap items-center gap-x-6 gap-y-2 text-xs">
            <span v-if="task.creator" class="flex items-center gap-1">
              <Icon name="lucide:user" class="size-3" />
              Created by {{ task.creator.name }}
            </span>
            <span v-if="task.created_at" class="flex items-center gap-1">
              <Icon name="lucide:calendar-plus" class="size-3" />
              {{ formatDateTime(task.created_at) }}
            </span>
            <span v-if="task.updated_at" class="flex items-center gap-1">
              <Icon name="lucide:clock" class="size-3" />
              Updated {{ formatDateTime(task.updated_at) }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Label } from "@/components/ui/label";
import Avatar from "@/components/Avatar.vue";
import BackButton from "@/components/BackButton.vue";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const route = useRoute();
const ulid = route.params.ulid;

// Fetch task details
const { data: response, pending, error } = await useLazySanctumFetch(`/api/tasks/${ulid}`, {
  key: `task-${ulid}`,
});
const task = computed(() => response.value?.data);

const formatDateTime = (date) => {
  if (!date) return '';
  return new Date(date).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
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

const statusBadgeClass = (status) => {
  const classes = {
    todo: 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-400',
    in_progress: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    completed: 'border-green-200 bg-green-50 text-green-700 dark:border-green-800 dark:bg-green-900/30 dark:text-green-400',
    archived: 'border-gray-200 bg-gray-100 text-gray-500 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-500',
  };
  return classes[status] || '';
};

const statusIcon = (status) => {
  const icons = {
    todo: 'lucide:circle',
    in_progress: 'lucide:loader',
    completed: 'lucide:check-circle',
    archived: 'lucide:archive',
  };
  return icons[status] || 'lucide:circle';
};

const visibilityIcon = (visibility) => {
  const icons = {
    public: 'lucide:globe',
    private: 'lucide:lock',
    shared: 'lucide:users',
  };
  return icons[visibility] || 'lucide:eye';
};

const formatStatus = (status) => {
  return status.replace('_', ' ');
};

// Set page meta
useHead({
  title: computed(() => task.value ? `${task.value.title} - Tasks` : 'Task Details'),
});
</script>
