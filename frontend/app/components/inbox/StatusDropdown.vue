<template>
  <DropdownMenu>
    <DropdownMenuTrigger asChild>
      <button
        class="text-muted-foreground hover:text-foreground data-[state=open]:bg-muted inline-flex items-center gap-x-1.5 rounded-md px-2 py-1 text-xs font-medium tracking-tight transition hover:bg-muted active:scale-98"
      >
        <span :class="config.dot" class="size-2 rounded-full" />
        {{ config.label }}
        <Icon name="lucide:chevron-down" class="size-3 opacity-60" />
      </button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="start" class="w-40">
      <DropdownMenuItem
        v-for="s in statuses"
        :key="s.value"
        :disabled="disabled || status === s.value"
        class="gap-x-2"
        @click="$emit('update', s.value)"
      >
        <span :class="statusConfigs[s.value].dot" class="size-2 rounded-full" />
        {{ s.label }}
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>

<script setup>
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

const props = defineProps({
  status: { type: String, required: true },
  disabled: { type: Boolean, default: false },
});

defineEmits(["update"]);

const statuses = [
  { value: "new", label: "New" },
  { value: "in_progress", label: "In Progress" },
  { value: "completed", label: "Completed" },
  { value: "archived", label: "Archived" },
];

const statusConfigs = {
  new: { label: "New", dot: "bg-blue-500" },
  in_progress: { label: "In Progress", dot: "bg-yellow-500" },
  completed: { label: "Completed", dot: "bg-green-500" },
  archived: { label: "Archived", dot: "bg-gray-400" },
};

const config = computed(() => statusConfigs[props.status] || statusConfigs.new);
</script>
