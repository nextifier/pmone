<template>
  <div
    :data-id="task.id"
    class="group flex items-start gap-x-1 rounded-lg px-1 py-1.5"
    :class="task.status === 'completed' ? 'opacity-60' : ''"
  >
    <!-- Drag Handle -->
    <div
      class="drag-handle text-muted-foreground hover:text-foreground flex h-7 w-7 shrink-0 cursor-grab items-center justify-center rounded-md active:cursor-grabbing"
    >
      <Icon name="lucide:grip-vertical" class="size-4" />
    </div>

    <!-- Checkbox -->
    <div class="flex h-7 shrink-0 items-center">
      <Checkbox :model-value="task.status === 'completed'" @update:model-value="toggleCompleted" />
    </div>

    <!-- Title + Details -->
    <div class="min-w-0 flex-1">
      <button
        type="button"
        @click="$emit('view', task)"
        class="w-full cursor-pointer text-left text-sm tracking-tight hover:underline"
        :class="
          task.status === 'completed' ? 'text-muted-foreground line-through' : 'text-foreground'
        "
      >
        {{ task.title }}
      </button>

      <!-- Details row -->
      <div
        v-if="showDetails"
        class="text-muted-foreground mt-0.5 flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-[11px]"
      >
        <span v-if="task.priority || task.complexity" class="flex items-center gap-x-1.5">
          <PriorityBars v-if="task.priority" :level="task.priority" label="Priority" />
          <span v-if="task.priority && task.complexity" class="bg-primary/20 h-3 w-px"></span>
          <PriorityBars v-if="task.complexity" :level="task.complexity" label="Complexity" />
        </span>
        <span
          v-if="task.estimated_start_at || task.estimated_completion_at"
          class="flex items-center gap-x-1"
        >
          <Icon name="hugeicons:calendar-01" class="size-3" />
          <span v-if="task.estimated_start_at">{{ formatDateShort(task.estimated_start_at) }}</span>
          <span v-if="task.estimated_start_at && task.estimated_completion_at">-</span>
          <span v-if="task.estimated_completion_at">{{
            formatDateShort(task.estimated_completion_at)
          }}</span>
        </span>
        <span v-if="task.project" class="flex items-center gap-x-1">
          <Avatar :model="task.project" size="sm" class="size-5" rounded="rounded" />
          <span class="truncate">{{ task.project.name }}</span>
        </span>
      </div>
    </div>

    <!-- Actions Dropdown -->
    <DropdownMenu>
      <DropdownMenuTrigger as-child>
        <button
          type="button"
          class="text-muted-foreground hover:text-foreground hover:bg-muted flex size-7 shrink-0 items-center justify-center rounded-full"
        >
          <Icon name="hugeicons:more-vertical" class="size-4.5" />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" class="w-44">
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
});

const emit = defineEmits(["updateStatus", "delete", "view", "edit"]);

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
