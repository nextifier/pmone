<template>
  <div
    :data-id="task.id"
    class="group flex items-start gap-x-2"
    :class="task.status === 'completed' ? '' : ''"
  >
    <!-- Drag Handle (only if canEdit) -->
    <div
      v-if="canEdit"
      class="drag-handle text-muted-foreground hover:text-foreground flex h-7 w-4.5 shrink-0 cursor-grab items-center justify-center rounded-md active:cursor-grabbing"
    >
      <Icon name="lucide:grip-vertical" class="size-4" />
    </div>

    <!-- Checkbox (only if canEdit) -->
    <div v-if="canEdit" class="flex h-7 shrink-0 items-center">
      <Checkbox :model-value="task.status === 'completed'" @update:model-value="toggleCompleted" />
    </div>

    <!-- Title + Details -->
    <div class="mx-1.5 mt-px min-w-0 grow">
      <textarea
        ref="editInputEl"
        :value="isEditing ? editTitle : task.title"
        :readonly="!isEditing"
        class="decoration-muted-foreground/75 mt-px field-sizing-content min-h-0 w-full resize-none rounded-xs text-base tracking-tight outline-none"
        :class="
          isEditing
            ? 'ring-offset-card ring-1 ring-offset-5'
            : [
                'cursor-pointer bg-transparent',
                task.status === 'completed'
                  ? 'text-muted-foreground line-through'
                  : 'text-foreground',
              ]
        "
        rows="1"
        @click="handleTitleClick"
        @input="editTitle = $event.target.value"
        @blur="isEditing && saveTitle()"
        @keydown.enter.prevent="isEditing && saveTitle()"
        @keydown.escape="isEditing && cancelEdit()"
      />

      <!-- Details row -->
      <div
        v-if="showDetails"
        class="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-2 text-sm"
      >
        <PriorityBars v-if="task.priority" :level="task.priority" label="Priority" />

        <PriorityBars v-if="task.complexity" :level="task.complexity" label="Complexity" />

        <span
          v-if="task.estimated_start_at || task.estimated_completion_at"
          class="flex items-center gap-x-1 text-sm tracking-tight"
        >
          <Icon name="hugeicons:calendar-03" class="size-4.5 shrink-0" />
          <span v-if="task.estimated_start_at">{{ formatDateShort(task.estimated_start_at) }}</span>
          <span v-if="task.estimated_start_at && task.estimated_completion_at">-</span>
          <span v-if="task.estimated_completion_at">{{
            formatDateShort(task.estimated_completion_at)
          }}</span>
        </span>

        <span v-if="task.project" class="flex items-center gap-x-1">
          <Avatar :model="task.project" size="sm" class="size-6" rounded="rounded-md" />
          <span class="truncate tracking-tight">{{ task.project.name }}</span>
        </span>
      </div>
    </div>

    <!-- Actions Dropdown (only if canEdit) -->
    <DropdownMenu v-if="canEdit">
      <DropdownMenuTrigger as-child>
        <button
          type="button"
          class="text-muted-foreground hover:text-foreground hover:bg-muted flex size-7 shrink-0 items-center justify-center rounded-full"
        >
          <Icon name="hugeicons:more-vertical" class="size-4.5" />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" class="w-44">
        <DropdownMenuItem @click="$emit('view', task)">
          <Icon name="hugeicons:view" class="size-4" />
          <span>View Details</span>
        </DropdownMenuItem>
        <DropdownMenuItem @click="$emit('edit', task)">
          <Icon name="hugeicons:pencil-edit-01" class="size-4" />
          <span>Edit</span>
        </DropdownMenuItem>
        <DropdownMenuItem v-if="task.status !== 'completed'" @click="togglePin">
          <Icon name="hugeicons:pin" class="size-4" />
          <span>{{ task.status === "in_progress" ? "Move to To Do" : "Start Working" }}</span>
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem class="text-destructive" @click="$emit('delete', task)">
          <Icon name="hugeicons:delete-02" class="size-4" />
          <span>Delete</span>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  </div>
</template>

<script setup>
import Avatar from "@/components/Avatar.vue";
import PriorityBars from "@/components/task/PriorityBars.vue";
import { Checkbox } from "@/components/ui/checkbox";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

const props = defineProps({
  task: {
    type: Object,
    required: true,
  },
  showDetails: {
    type: Boolean,
    default: false,
  },
  canEdit: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["updateStatus", "updateTitle", "delete", "view", "edit"]);

// Inline edit state
const isEditing = ref(false);
const editTitle = ref("");
const editInputEl = ref(null);

const handleTitleClick = () => {
  if (props.canEdit) {
    startEditing();
  } else {
    emit("view", props.task);
  }
};

const startEditing = () => {
  editTitle.value = props.task.title;
  isEditing.value = true;
  nextTick(() => editInputEl.value?.focus());
};

const saveTitle = () => {
  const newTitle = editTitle.value.trim();
  if (!newTitle || newTitle === props.task.title) {
    cancelEdit();
    return;
  }
  emit("updateTitle", props.task, newTitle);
  isEditing.value = false;
};

const cancelEdit = () => {
  isEditing.value = false;
  editTitle.value = "";
};

const formatDateShort = (date) => {
  if (!date) return "";
  return new Date(date).toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
  });
};

const toggleCompleted = (checked) => {
  emit("updateStatus", props.task, checked ? "completed" : "todo");
};

const togglePin = () => {
  emit("updateStatus", props.task, props.task.status === "in_progress" ? "todo" : "in_progress");
};
</script>
