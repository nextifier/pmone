<template>
  <form @submit.prevent="handleSubmit" class="space-y-6">
    <div class="border-border bg-card space-y-6 rounded-lg border p-6">
      <!-- Title -->
      <div>
        <label for="title" class="text-foreground mb-2 block text-sm font-medium">
          Task Title <span class="text-destructive">*</span>
        </label>
        <input
          id="title"
          v-model="form.title"
          type="text"
          required
          maxlength="255"
          placeholder="Enter task title..."
          class="border-border bg-background w-full rounded-md border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
        />
      </div>

      <!-- Description -->
      <div>
        <label for="description" class="text-foreground mb-2 block text-sm font-medium">
          Description
        </label>
        <!-- TODO: Integrate TipTap Editor here -->
        <!-- For now, using simple textarea as placeholder -->
        <textarea
          id="description"
          v-model="form.description"
          rows="6"
          placeholder="Describe the task in detail... (TipTap editor will be integrated here)"
          class="border-border bg-background w-full rounded-md border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
        ></textarea>
        <p class="text-muted-foreground mt-1 text-xs">
          Rich text editor with image support will be integrated (TipTap)
        </p>
      </div>

      <!-- Row: Status, Priority, Complexity -->
      <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <!-- Status -->
        <div>
          <label for="status" class="text-foreground mb-2 block text-sm font-medium">Status</label>
          <select
            id="status"
            v-model="form.status"
            class="border-border bg-background w-full rounded-md border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
          >
            <option value="todo">To Do</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
            <option value="archived">Archived</option>
          </select>
        </div>

        <!-- Priority -->
        <div>
          <label for="priority" class="text-foreground mb-2 block text-sm font-medium">Priority</label>
          <select
            id="priority"
            v-model="form.priority"
            class="border-border bg-background w-full rounded-md border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
          >
            <option :value="null">None</option>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
          </select>
        </div>

        <!-- Complexity -->
        <div>
          <label for="complexity" class="text-foreground mb-2 block text-sm font-medium">Complexity</label>
          <select
            id="complexity"
            v-model="form.complexity"
            class="border-border bg-background w-full rounded-md border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
          >
            <option :value="null">None</option>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
          </select>
        </div>
      </div>

      <!-- Row: Estimated Times -->
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <!-- Estimated Start -->
        <div>
          <label for="estimated_start_at" class="text-foreground mb-2 block text-sm font-medium">
            Estimated Start Time
          </label>
          <input
            id="estimated_start_at"
            v-model="form.estimated_start_at"
            type="datetime-local"
            class="border-border bg-background w-full rounded-md border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
          />
        </div>

        <!-- Estimated Completion -->
        <div>
          <label for="estimated_completion_at" class="text-foreground mb-2 block text-sm font-medium">
            Estimated Completion Time
          </label>
          <input
            id="estimated_completion_at"
            v-model="form.estimated_completion_at"
            type="datetime-local"
            :min="form.estimated_start_at"
            class="border-border bg-background w-full rounded-md border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
          />
        </div>
      </div>

      <!-- Assignee (TODO: Replace with user picker component) -->
      <div>
        <label for="assignee_id" class="text-foreground mb-2 block text-sm font-medium">
          Assign To
        </label>
        <input
          id="assignee_id"
          v-model.number="form.assignee_id"
          type="number"
          placeholder="User ID (TODO: Replace with UserSelect component)"
          class="border-border bg-background w-full rounded-md border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
        />
        <p class="text-muted-foreground mt-1 text-xs">
          User picker component will be integrated here
        </p>
      </div>

      <!-- Project (TODO: Replace with project picker component) -->
      <div>
        <label for="project_id" class="text-foreground mb-2 block text-sm font-medium">
          Link to Project (Optional)
        </label>
        <input
          id="project_id"
          v-model.number="form.project_id"
          type="number"
          placeholder="Project ID (TODO: Replace with ProjectSelect component)"
          class="border-border bg-background w-full rounded-md border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
        />
        <p class="text-muted-foreground mt-1 text-xs">
          Project picker component will be integrated here
        </p>
      </div>

      <!-- Visibility -->
      <div>
        <label class="text-foreground mb-2 block text-sm font-medium">
          Who can view this task? <span class="text-destructive">*</span>
        </label>
        <div class="space-y-2">
          <label
            v-for="option in visibilityOptions"
            :key="option.value"
            :class="{
              'border-primary bg-primary/10': form.visibility === option.value,
            }"
            class="border-border flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-colors hover:bg-muted"
          >
            <input
              v-model="form.visibility"
              type="radio"
              :value="option.value"
              class="mt-1"
            />
            <div class="flex-1">
              <div class="text-foreground font-medium">{{ option.label }}</div>
              <div class="text-muted-foreground text-sm">{{ option.description }}</div>
            </div>
          </label>
        </div>
      </div>

      <!-- Shared Users (only if visibility is 'shared') -->
      <div v-if="form.visibility === 'shared'">
        <label class="text-foreground mb-2 block text-sm font-medium">
          Share with Users <span class="text-destructive">*</span>
        </label>
        <div class="border-border bg-muted rounded-lg border p-4">
          <p class="text-muted-foreground mb-2 text-sm">
            TODO: Implement UserMultiSelect component with role selector (viewer/editor)
          </p>
          <input
            v-model="sharedUserIdsInput"
            type="text"
            placeholder="Enter user IDs separated by comma (temporary)"
            class="border-border bg-background w-full rounded-md border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
          />
          <p class="text-muted-foreground mt-1 text-xs">
            Example: 1,2,3 (will default to viewer role)
          </p>
        </div>
      </div>
    </div>

    <!-- Form Actions -->
    <div class="flex items-center justify-end gap-3">
      <button
        type="button"
        @click="$emit('cancel')"
        :disabled="loading"
        class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
      >
        Cancel
      </button>
      <button
        type="submit"
        :disabled="loading || !isFormValid"
        class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
      >
        <Spinner v-if="loading" class="size-4" />
        <span>{{ task ? 'Update Task' : 'Create Task' }}</span>
      </button>
    </div>
  </form>
</template>

<script setup>
const props = defineProps({
  task: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['submit', 'cancel']);

// Form data
const form = reactive({
  title: props.task?.title || '',
  description: props.task?.description || '',
  status: props.task?.status || 'todo',
  priority: props.task?.priority || null,
  complexity: props.task?.complexity || null,
  visibility: props.task?.visibility || 'private',
  assignee_id: props.task?.assignee_id || null,
  project_id: props.task?.project_id || null,
  estimated_start_at: props.task?.estimated_start_at
    ? formatDateTimeLocal(props.task.estimated_start_at)
    : '',
  estimated_completion_at: props.task?.estimated_completion_at
    ? formatDateTimeLocal(props.task.estimated_completion_at)
    : '',
  shared_user_ids: props.task?.shared_users?.map((u) => u.id) || [],
  shared_roles: props.task?.shared_users?.reduce((acc, u) => {
    acc[u.id] = u.role;
    return acc;
  }, {}) || {},
});

// Temporary input for shared user IDs (comma-separated)
const sharedUserIdsInput = ref(
  props.task?.shared_users?.map((u) => u.id).join(',') || ''
);

const visibilityOptions = [
  {
    value: 'public',
    label: 'Public',
    description: 'Anyone can view this task',
  },
  {
    value: 'private',
    label: 'Private',
    description: 'Only you can view this task',
  },
  {
    value: 'shared',
    label: 'Shared',
    description: 'Selected users can view/edit',
  },
];

const isFormValid = computed(() => {
  if (!form.title.trim()) return false;
  if (!form.visibility) return false;
  if (form.visibility === 'shared' && form.shared_user_ids.length === 0) {
    return false;
  }
  return true;
});

const formatDateTimeLocal = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  return `${year}-${month}-${day}T${hours}:${minutes}`;
};

// Watch shared user IDs input to update form
watch(sharedUserIdsInput, (value) => {
  if (value) {
    const ids = value
      .split(',')
      .map((id) => parseInt(id.trim()))
      .filter((id) => !isNaN(id));
    form.shared_user_ids = ids;

    // Set default role to viewer for all
    const roles = {};
    ids.forEach((id) => {
      roles[id] = 'viewer';
    });
    form.shared_roles = roles;
  } else {
    form.shared_user_ids = [];
    form.shared_roles = {};
  }
});

const handleSubmit = () => {
  if (!isFormValid.value) return;

  const payload = {
    title: form.title.trim(),
    description: form.description || null,
    status: form.status,
    priority: form.priority || null,
    complexity: form.complexity || null,
    visibility: form.visibility,
    assignee_id: form.assignee_id || null,
    project_id: form.project_id || null,
    estimated_start_at: form.estimated_start_at || null,
    estimated_completion_at: form.estimated_completion_at || null,
  };

  // Add shared users if visibility is shared
  if (form.visibility === 'shared' && form.shared_user_ids.length > 0) {
    payload.shared_user_ids = form.shared_user_ids;
    payload.shared_roles = form.shared_roles;
  }

  emit('submit', payload);
};
</script>
