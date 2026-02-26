<template>
  <form @submit.prevent="handleSubmit" class="space-y-6">
    <div class="space-y-5">
      <!-- Title -->
      <div class="space-y-2">
        <Label for="title">Task Title <span class="text-destructive">*</span></Label>
        <Textarea
          ref="titleInputRef"
          id="title"
          v-model="form.title"
          required
          rows="1"
          placeholder="Enter task title..."
          class="min-h-0 resize-none text-base"
          :class="{ 'border-destructive': errors.title }"
        />
        <p v-if="errors.title" class="text-destructive text-xs">{{ errors.title[0] }}</p>
      </div>

      <!-- Description -->
      <div class="space-y-2">
        <Label for="description">Description</Label>
        <TipTapEditor
          v-model="form.description"
          model-type="App\Models\Task"
          collection="description_images"
          :sticky="false"
          min-height="150px"
          placeholder="Describe the task in detail..."
        />
        <p v-if="errors.description" class="text-destructive text-xs">
          {{ errors.description[0] }}
        </p>
      </div>

      <!-- Row: Status, Priority, Complexity -->
      <div class="grid grid-cols-3 gap-2">
        <!-- Status -->
        <div class="space-y-2">
          <Label for="status">Status</Label>
          <Select v-model="form.status">
            <SelectTrigger class="w-full">
              <SelectValue placeholder="Select status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="todo">To Do</SelectItem>
              <SelectItem value="in_progress">In Progress</SelectItem>
              <SelectItem value="completed">Completed</SelectItem>
              <SelectItem value="archived">Archived</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="errors.status" class="text-destructive text-xs">{{ errors.status[0] }}</p>
        </div>

        <!-- Priority -->
        <div class="space-y-2">
          <Label for="priority">Priority</Label>
          <Select v-model="form.priority">
            <SelectTrigger class="w-full">
              <SelectValue placeholder="Select priority" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem :value="null">None</SelectItem>
              <SelectItem value="low">Low</SelectItem>
              <SelectItem value="medium">Medium</SelectItem>
              <SelectItem value="high">High</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="errors.priority" class="text-destructive text-xs">{{ errors.priority[0] }}</p>
        </div>

        <!-- Complexity -->
        <div class="space-y-2">
          <Label for="complexity">Complexity</Label>
          <Select v-model="form.complexity">
            <SelectTrigger class="w-full">
              <SelectValue placeholder="Select complexity" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem :value="null">None</SelectItem>
              <SelectItem value="low">Low</SelectItem>
              <SelectItem value="medium">Medium</SelectItem>
              <SelectItem value="high">High</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="errors.complexity" class="text-destructive text-xs">
            {{ errors.complexity[0] }}
          </p>
        </div>
      </div>

      <!-- Row: Estimated Times -->
      <div class="grid grid-cols-2 gap-2">
        <!-- Estimated Start -->
        <div class="space-y-2">
          <Label for="estimated_start_at">Start Time</Label>
          <DateTimePicker
            v-model="form.estimated_start_at"
            placeholder="Select start time"
            :default-hour="9"
          />
          <p v-if="errors.estimated_start_at" class="text-destructive text-xs">
            {{ errors.estimated_start_at[0] }}
          </p>
        </div>

        <!-- Estimated Completion -->
        <div class="space-y-2">
          <Label for="estimated_completion_at">Completion Time</Label>
          <DateTimePicker
            v-model="form.estimated_completion_at"
            placeholder="Select completion time"
            :default-hour="17"
          />
          <p v-if="errors.estimated_completion_at" class="text-destructive text-xs">
            {{ errors.estimated_completion_at[0] }}
          </p>
        </div>
      </div>

      <!-- Assignee -->
      <div class="space-y-2">
        <Label for="assignee_id">Assign To</Label>
        <UserMultiSelect
          v-if="eligibleUsers.length > 0"
          :users="eligibleUsers"
          v-model="selectedAssignee"
          placeholder="Search users to assign..."
          :max-selected="1"
        />
        <Input
          v-else
          id="assignee_id"
          v-model.number="form.assignee_id"
          type="number"
          placeholder="User ID"
          :class="{ 'border-destructive': errors.assignee_id }"
        />
        <p v-if="errors.assignee_id" class="text-destructive text-xs">
          {{ errors.assignee_id[0] }}
        </p>
        <!-- <p class="text-muted-foreground text-xs">Optional: Assign this task to a user</p> -->
      </div>

      <!-- Project -->
      <div class="space-y-2">
        <Label for="project_id">Link to Project</Label>
        <Select v-model="form.project_id">
          <SelectTrigger class="w-full">
            <template #default>
              <div v-if="selectedProject" class="flex items-center gap-2">
                <Avatar :model="selectedProject" size="sm" class="size-5" rounded="rounded" />
                <span class="truncate">{{ selectedProject.name }}</span>
              </div>
              <span v-else class="text-muted-foreground">Select a project</span>
            </template>
          </SelectTrigger>
          <SelectContent>
            <SelectItem :value="null">
              <div class="flex items-center gap-2">
                <div class="bg-muted flex size-5 items-center justify-center rounded">
                  <Icon name="lucide:minus" class="text-muted-foreground size-3" />
                </div>
                <span>None</span>
              </div>
            </SelectItem>
            <SelectItem v-for="project in eligibleProjects" :key="project.id" :value="project.id">
              <div class="flex items-center gap-2">
                <Avatar :model="project" size="sm" class="size-5" rounded="rounded" />
                <span class="truncate">{{ project.name }}</span>
              </div>
            </SelectItem>
          </SelectContent>
        </Select>
        <p v-if="errors.project_id" class="text-destructive text-xs">{{ errors.project_id[0] }}</p>
      </div>

      <!-- Visibility -->
      <div class="space-y-2.5">
        <Label> Who can view this task? <span class="text-destructive">*</span> </Label>
        <RadioGroup v-model="form.visibility" class="space-y-0">
          <div
            v-for="option in visibilityOptions"
            :key="option.value"
            :class="{
              'border-primary bg-primary/5': form.visibility === option.value,
            }"
            class="border-border hover:bg-muted flex cursor-pointer items-start gap-x-2 rounded-lg border p-4 transition-colors"
          >
            <RadioGroupItem
              :value="option.value"
              :id="`visibility-${option.value}`"
              class="mt-0.5"
            />
            <Label :for="`visibility-${option.value}`" class="flex-1 cursor-pointer">
              <div class="text-foreground font-medium">{{ option.label }}</div>
              <div class="text-muted-foreground text-sm font-normal">{{ option.description }}</div>
            </Label>
          </div>
        </RadioGroup>
        <p v-if="errors.visibility" class="text-destructive text-xs">{{ errors.visibility[0] }}</p>
      </div>

      <!-- Shared Users (only if visibility is 'shared') -->
      <div v-if="form.visibility === 'shared'" class="space-y-2">
        <Label> Share with Users <span class="text-destructive">*</span> </Label>
        <UserMultiSelect
          v-if="eligibleUsers.length > 0"
          :users="eligibleUsers"
          v-model="selectedSharedUsers"
          placeholder="Search users to share with..."
        />
        <div v-else class="border-border bg-muted rounded-lg border p-4">
          <Input
            v-model="sharedUserIdsInput"
            type="text"
            placeholder="Enter user IDs separated by comma"
            :class="{ 'border-destructive': errors.shared_user_ids }"
          />
          <p class="text-muted-foreground mt-2 text-xs">
            Example: 1,2,3 (will default to viewer role)
          </p>
        </div>
        <p v-if="errors.shared_user_ids" class="text-destructive text-xs">
          {{ errors.shared_user_ids[0] }}
        </p>
      </div>
    </div>

    <!-- Form Actions -->
    <div class="flex items-center justify-end gap-3">
      <Button type="button" variant="outline" @click="$emit('cancel')" :disabled="loading">
        Cancel
      </Button>
      <Button type="submit" :disabled="loading || !isFormValid">
        <Spinner v-if="loading" class="mr-2 size-4" />
        <span>{{ task ? "Update Task" : "Create Task" }}</span>
      </Button>
    </div>
  </form>
</template>

<script setup>
import Avatar from "@/components/Avatar.vue";
import DateTimePicker from "@/components/DateTimePicker.vue";
import TipTapEditor from "@/components/TipTapEditor.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { Textarea } from "@/components/ui/textarea";
import UserMultiSelect from "@/components/user/MultiSelect.vue";

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

const emit = defineEmits(["submit", "cancel"]);

const { user: currentUser } = useSanctumAuth();

// Form data
const form = reactive({
  title: props.task?.title || "",
  description: props.task?.description || "",
  status: props.task?.status || "todo",
  priority: props.task?.priority || null,
  complexity: props.task?.complexity || null,
  visibility: props.task?.visibility || "public",
  assignee_id: props.task?.assignee_id ?? currentUser.value?.id ?? null,
  project_id: props.task?.project_id || props.task?.project?.id || null,
  estimated_start_at: props.task?.estimated_start_at
    ? new Date(props.task.estimated_start_at)
    : null,
  estimated_completion_at: props.task?.estimated_completion_at
    ? new Date(props.task.estimated_completion_at)
    : null,
  shared_user_ids: props.task?.shared_users?.map((u) => u.id) || [],
  shared_roles:
    props.task?.shared_users?.reduce((acc, u) => {
      acc[u.id] = u.role;
      return acc;
    }, {}) || {},
});

const titleInputRef = ref(null);
const errors = ref({});

// Temporary input for shared user IDs (comma-separated) - fallback when UserMultiSelect not available
const sharedUserIdsInput = ref(props.task?.shared_users?.map((u) => u.id).join(",") || "");

// Selected users for UserMultiSelect
const selectedAssignee = ref(
  props.task?.assignee ? [props.task.assignee] : currentUser.value ? [currentUser.value] : []
);
const selectedSharedUsers = ref(props.task?.shared_users || []);

// Fetch eligible users and projects
const sanctumClient = useSanctumClient();
const eligibleUsers = ref([]);
const eligibleProjects = ref([]);

onMounted(async () => {
  try {
    // Fetch eligible users for assignment
    const usersResponse = await sanctumClient("/api/users?per_page=100");
    eligibleUsers.value = usersResponse.data || [];
  } catch (err) {
    console.error("Failed to fetch eligible users:", err);
  }

  try {
    // Fetch projects for linking
    const projectsResponse = await sanctumClient("/api/projects?per_page=100");
    eligibleProjects.value = projectsResponse.data || [];
  } catch (err) {
    console.error("Failed to fetch projects:", err);
  }
});

// Watch selected assignee
watch(selectedAssignee, (users) => {
  form.assignee_id = users.length > 0 ? users[0].id : null;
});

// Watch selected shared users
watch(selectedSharedUsers, (users) => {
  form.shared_user_ids = users.map((u) => u.id);
  const roles = {};
  users.forEach((u) => {
    roles[u.id] = form.shared_roles[u.id] || "viewer";
  });
  form.shared_roles = roles;
});

// Selected project computed
const selectedProject = computed(() => {
  if (!form.project_id) return null;
  return eligibleProjects.value.find((p) => p.id === form.project_id);
});

const visibilityOptions = [
  {
    value: "public",
    label: "Public",
    description: "Anyone can view this task",
  },
  {
    value: "private",
    label: "Private",
    description: "Only you can view this task",
  },
  {
    value: "shared",
    label: "Shared",
    description: "Selected users can view/edit",
  },
];

const isDirty = computed(() => {
  if (!props.task) {
    // Create mode: dirty if title has content
    return form.title.trim().length > 0;
  }
  // Edit mode: dirty if any field changed from original
  return (
    form.title !== (props.task.title || "") ||
    form.description !== (props.task.description || "") ||
    form.status !== (props.task.status || "todo") ||
    form.priority !== (props.task.priority || null) ||
    form.complexity !== (props.task.complexity || null) ||
    form.visibility !== (props.task.visibility || "public") ||
    form.assignee_id !== (props.task.assignee_id ?? currentUser.value?.id ?? null) ||
    form.project_id !== (props.task.project_id || props.task.project?.id || null)
  );
});

const isFormValid = computed(() => {
  if (!form.title.trim()) return false;
  if (!form.visibility) return false;
  if (form.visibility === "shared" && form.shared_user_ids.length === 0) {
    return false;
  }
  return true;
});

// Format Date to local datetime string for backend (YYYY-MM-DD HH:mm:ss)
// Uses local time components to avoid timezone conversion issues
const formatDateTimeForBackend = (date) => {
  if (!date) return null;
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  const hours = String(date.getHours()).padStart(2, "0");
  const minutes = String(date.getMinutes()).padStart(2, "0");
  const seconds = "00";
  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
};

// Watch shared user IDs input to update form (fallback)
watch(sharedUserIdsInput, (value) => {
  if (value && eligibleUsers.value.length === 0) {
    const ids = value
      .split(",")
      .map((id) => parseInt(id.trim()))
      .filter((id) => !isNaN(id));
    form.shared_user_ids = ids;

    // Set default role to viewer for all
    const roles = {};
    ids.forEach((id) => {
      roles[id] = "viewer";
    });
    form.shared_roles = roles;
  }
});

const getPayload = () => {
  if (!isFormValid.value) return null;

  const payload = {
    title: form.title.trim(),
    description: form.description || null,
    status: form.status,
    priority: form.priority || null,
    complexity: form.complexity || null,
    visibility: form.visibility,
    assignee_id: form.assignee_id || null,
    project_id: form.project_id || null,
    estimated_start_at: formatDateTimeForBackend(form.estimated_start_at),
    estimated_completion_at: formatDateTimeForBackend(form.estimated_completion_at),
  };

  if (form.visibility === "shared" && form.shared_user_ids.length > 0) {
    payload.shared_user_ids = form.shared_user_ids;
    payload.shared_roles = form.shared_roles;
  }

  return payload;
};

const handleSubmit = () => {
  const payload = getPayload();
  if (!payload) return;

  errors.value = {};
  emit("submit", payload);
};

// Expose for parent component
const resetForm = () => {
  form.title = props.task?.title || "";
  form.description = props.task?.description || "";
  form.status = props.task?.status || "todo";
  form.priority = props.task?.priority || null;
  form.complexity = props.task?.complexity || null;
  form.visibility = props.task?.visibility || "public";
  form.assignee_id = props.task?.assignee_id ?? currentUser.value?.id ?? null;
  form.project_id = props.task?.project_id || props.task?.project?.id || null;
  form.estimated_start_at = props.task?.estimated_start_at
    ? new Date(props.task.estimated_start_at)
    : null;
  form.estimated_completion_at = props.task?.estimated_completion_at
    ? new Date(props.task.estimated_completion_at)
    : null;
  form.shared_user_ids = props.task?.shared_users?.map((u) => u.id) || [];
  form.shared_roles =
    props.task?.shared_users?.reduce((acc, u) => {
      acc[u.id] = u.role;
      return acc;
    }, {}) || {};
  selectedAssignee.value = props.task?.assignee
    ? [props.task.assignee]
    : currentUser.value
      ? [currentUser.value]
      : [];
  selectedSharedUsers.value = props.task?.shared_users || [];
  sharedUserIdsInput.value = props.task?.shared_users?.map((u) => u.id).join(",") || "";
  errors.value = {};
};

defineExpose({
  handleSubmit,
  getPayload,
  isDirty,
  resetForm,
  focusTitle: () => {
    nextTick(() => {
      titleInputRef.value?.$el?.focus();
    });
  },
  setErrors: (newErrors) => {
    errors.value = newErrors;
  },
});

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => {
      handleSubmit();
    },
  },
});
</script>
