<template>
  <div class="mx-auto max-w-4xl space-y-6 pt-4 pb-16">
    <div v-if="pending" class="flex justify-center py-12">
      <Spinner class="size-8" />
    </div>

    <div v-else-if="error" class="border-border bg-card rounded-lg border p-12 text-center">
      <Icon name="lucide:alert-circle" class="text-destructive mx-auto mb-3 size-12" />
      <p class="text-muted-foreground text-sm">Failed to load task. Please try again.</p>
    </div>

    <div v-else-if="task" class="space-y-6">
      <!-- Header -->
      <div class="flex items-start justify-between gap-4">
        <div class="flex-1 space-y-2">
          <div class="flex items-center gap-2">
            <h1 class="text-2xl font-bold tracking-tight">{{ task.title }}</h1>
            <span
              v-if="task.priority"
              :class="{
                'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300': task.priority === 'high',
                'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300': task.priority === 'medium',
                'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300': task.priority === 'low',
              }"
              class="rounded-full px-3 py-1 text-sm font-medium"
            >
              {{ task.priority }} priority
            </span>
          </div>

          <div class="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
            <span :class="{
              'text-gray-600 dark:text-gray-400': task.status === 'todo',
              'text-blue-600 dark:text-blue-400': task.status === 'in_progress',
              'text-green-600 dark:text-green-400': task.status === 'completed',
              'text-gray-500 dark:text-gray-500': task.status === 'archived',
            }">
              Status: {{ task.status.replace('_', ' ') }}
            </span>
            <span v-if="task.complexity">
              Complexity: {{ task.complexity }}
            </span>
            <span v-if="task.visibility">
              Visibility: {{ task.visibility }}
            </span>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <BackButton />
          <NuxtLink
            :to="`/tasks/${task.ulid}/edit`"
            class="hover:bg-primary/80 text-primary-foreground bg-primary flex items-center gap-x-1.5 rounded-md px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
          >
            <Icon name="lucide:pencil" class="size-4" />
            <span>Edit</span>
          </NuxtLink>
        </div>
      </div>

      <!-- Task Details Card -->
      <div class="border-border bg-card space-y-6 rounded-lg border p-6">
        <!-- Description -->
        <div v-if="task.description">
          <h3 class="text-foreground mb-2 text-sm font-semibold">Description</h3>
          <div class="prose dark:prose-invert text-muted-foreground max-w-none text-sm" v-html="task.description"></div>
        </div>

        <!-- Task Info Grid -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <!-- Assignee -->
          <div v-if="task.assignee">
            <h3 class="text-foreground mb-2 text-sm font-semibold">Assigned To</h3>
            <div class="flex items-center gap-2">
              <Avatar :user="task.assignee" size="sm" />
              <span class="text-muted-foreground text-sm">{{ task.assignee.name }}</span>
            </div>
          </div>

          <!-- Project -->
          <div v-if="task.project">
            <h3 class="text-foreground mb-2 text-sm font-semibold">Project</h3>
            <NuxtLink
              :to="`/projects/${task.project.username}`"
              class="text-primary hover:underline text-sm font-medium"
            >
              {{ task.project.name }}
            </NuxtLink>
          </div>

          <!-- Estimated Start -->
          <div v-if="task.estimated_start_at">
            <h3 class="text-foreground mb-2 text-sm font-semibold">Estimated Start</h3>
            <p class="text-muted-foreground text-sm">{{ formatDateTime(task.estimated_start_at) }}</p>
          </div>

          <!-- Estimated Completion -->
          <div v-if="task.estimated_completion_at">
            <h3 class="text-foreground mb-2 text-sm font-semibold">Estimated Completion</h3>
            <p :class="task.is_overdue ? 'text-destructive' : 'text-muted-foreground'" class="text-sm">
              {{ formatDateTime(task.estimated_completion_at) }}
              <span v-if="task.is_overdue" class="ml-1">(Overdue)</span>
            </p>
          </div>

          <!-- Completed At -->
          <div v-if="task.completed_at">
            <h3 class="text-foreground mb-2 text-sm font-semibold">Completed At</h3>
            <p class="text-muted-foreground text-sm">{{ formatDateTime(task.completed_at) }}</p>
          </div>
        </div>

        <!-- Shared Users -->
        <div v-if="task.shared_users && task.shared_users.length > 0">
          <h3 class="text-foreground mb-3 text-sm font-semibold">Shared With</h3>
          <div class="flex flex-wrap gap-2">
            <div
              v-for="sharedUser in task.shared_users"
              :key="sharedUser.id"
              class="border-border bg-muted flex items-center gap-2 rounded-full border px-3 py-1.5"
            >
              <Avatar :user="sharedUser" size="xs" />
              <span class="text-sm">{{ sharedUser.name }}</span>
              <span class="text-muted-foreground text-xs">({{ sharedUser.role }})</span>
            </div>
          </div>
        </div>

        <!-- Metadata -->
        <div class="border-border border-t pt-4">
          <div class="text-muted-foreground flex flex-wrap gap-x-6 gap-y-2 text-xs">
            <span v-if="task.creator">Created by {{ task.creator.name }}</span>
            <span v-if="task.created_at">{{ formatDateTime(task.created_at) }}</span>
            <span v-if="task.updated_at">Updated {{ formatDateTime(task.updated_at) }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import Avatar from "@/components/Avatar.vue";
import BackButton from "@/components/BackButton.vue";

definePageMeta({
  middleware: ['auth', 'verified'],
  layout: 'default',
});

const route = useRoute();
const ulid = route.params.ulid;

// Fetch task details
const { data: response, pending, error } = await useFetch(`/api/tasks/${ulid}`);
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

// Set page meta
useHead({
  title: computed(() => task.value ? `${task.value.title} - Tasks` : 'Task Details'),
});
</script>
