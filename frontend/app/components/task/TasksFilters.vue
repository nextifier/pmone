<template>
  <div class="grid gap-y-3">
    <div class="flex h-9 gap-x-2">
      <div class="relative flex h-full grow items-center">
        <Icon
          name="hugeicons:search-01"
          class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
        />
        <input
          ref="searchInputEl"
          v-model="localSearchQuery"
          type="text"
          :placeholder="searchPlaceholder"
          class="peer placeholder:text-muted-foreground h-full w-full rounded-md border bg-transparent px-9 py-1.5 text-sm tracking-tight focus:outline-hidden"
        />
        <span
          v-if="!localSearchQuery"
          class="text-muted-foreground/60 pointer-events-none absolute top-1/2 right-3 hidden -translate-y-1/2 items-center gap-x-1 text-xs font-medium peer-placeholder-shown:flex"
        >
          <kbd class="keyboard-symbol">{{ metaSymbol }} K</kbd>
        </span>
        <button
          v-if="localSearchQuery"
          @click="localSearchQuery = ''"
          class="bg-muted hover:bg-border absolute top-1/2 right-3 flex size-6 -translate-y-1/2 items-center justify-center rounded-full"
          aria-label="Clear search"
        >
          <Icon name="hugeicons:cancel-01" class="size-3 shrink-0" />
        </button>
      </div>

      <Popover>
        <PopoverTrigger asChild>
          <button
            class="hover:bg-muted relative flex h-9 shrink-0 items-center justify-center gap-x-1.5 rounded-md border px-3 text-sm tracking-tight active:scale-98"
          >
            <Icon name="hugeicons:filter" class="size-4 shrink-0" />
            <span>Filter</span>
            <span
              v-if="activeFilterCount > 0"
              class="bg-primary text-primary-foreground squircle absolute top-0 right-0 inline-flex size-4 translate-x-1/2 -translate-y-1/2 items-center justify-center text-[11px] font-medium tracking-tight"
            >
              {{ activeFilterCount }}
            </span>
          </button>
        </PopoverTrigger>
        <PopoverContent class="w-56 p-3" align="end">
          <div class="space-y-4">
            <!-- Status Filter -->
            <div class="space-y-2">
              <div class="text-muted-foreground text-xs font-medium">Status</div>
              <div class="space-y-2">
                <div
                  v-for="status in statusOptions"
                  :key="status.value"
                  class="flex items-center gap-2"
                >
                  <Checkbox
                    :id="`status-${status.value}`"
                    :model-value="localSelectedStatuses.includes(status.value)"
                    @update:model-value="(checked) => toggleStatus(status.value, checked)"
                  />
                  <Label
                    :for="`status-${status.value}`"
                    class="grow cursor-pointer font-normal tracking-tight"
                  >
                    {{ status.label }}
                  </Label>
                </div>
              </div>
            </div>

            <!-- Priority Filter -->
            <div class="space-y-2">
              <div class="text-muted-foreground text-xs font-medium">Priority</div>
              <div class="space-y-2">
                <div
                  v-for="priority in priorityOptions"
                  :key="priority.value"
                  class="flex items-center gap-2"
                >
                  <Checkbox
                    :id="`priority-${priority.value}`"
                    :model-value="localSelectedPriorities.includes(priority.value)"
                    @update:model-value="(checked) => togglePriority(priority.value, checked)"
                  />
                  <Label
                    :for="`priority-${priority.value}`"
                    class="grow cursor-pointer font-normal tracking-tight"
                  >
                    {{ priority.label }}
                  </Label>
                </div>
              </div>
            </div>
          </div>
        </PopoverContent>
      </Popover>
    </div>

    <div class="flex h-9 justify-end gap-x-1 sm:gap-x-2">
      <button
        v-if="hasActiveFilters"
        @click="clearFilters"
        class="hover:bg-muted flex aspect-square h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border text-sm tracking-tight active:scale-98 sm:aspect-auto sm:px-2.5"
      >
        <Icon name="hugeicons:cancel-01" class="size-4 shrink-0" />
        <span class="hidden sm:flex">Clear filters</span>
      </button>

      <button
        @click="$emit('refresh')"
        :disabled="pending"
        class="hover:bg-muted flex aspect-square h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border text-sm tracking-tight active:scale-98 sm:aspect-auto sm:px-2.5"
      >
        <Icon
          name="hugeicons:refresh"
          class="size-4 shrink-0"
          :class="pending ? 'animate-spin' : ''"
        />
        <span class="hidden sm:flex">Refresh</span>
      </button>

      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";

const props = defineProps({
  searchQuery: {
    type: String,
    default: "",
  },
  selectedStatuses: {
    type: Array,
    default: () => [],
  },
  selectedPriorities: {
    type: Array,
    default: () => [],
  },
  pending: {
    type: Boolean,
    default: false,
  },
  searchPlaceholder: {
    type: String,
    default: "Search tasks",
  },
});

const emit = defineEmits(["update:searchQuery", "update:selectedStatuses", "update:selectedPriorities", "refresh"]);

const statusOptions = [
  { value: "in_progress", label: "In Progress" },
  { value: "todo", label: "To Do" },
  { value: "completed", label: "Completed" },
  { value: "archived", label: "Archived" },
];

const priorityOptions = [
  { value: "high", label: "High" },
  { value: "medium", label: "Medium" },
  { value: "low", label: "Low" },
];

const localSearchQuery = computed({
  get: () => props.searchQuery,
  set: (value) => emit("update:searchQuery", value),
});

const localSelectedStatuses = computed({
  get: () => props.selectedStatuses,
  set: (value) => emit("update:selectedStatuses", value),
});

const localSelectedPriorities = computed({
  get: () => props.selectedPriorities,
  set: (value) => emit("update:selectedPriorities", value),
});

const hasActiveFilters = computed(() => {
  return localSearchQuery.value !== "" || localSelectedStatuses.value.length > 0 || localSelectedPriorities.value.length > 0;
});

const activeFilterCount = computed(() => {
  return localSelectedStatuses.value.length + localSelectedPriorities.value.length;
});

const toggleStatus = (status, checked) => {
  const updated = [...localSelectedStatuses.value];
  if (checked) {
    if (!updated.includes(status)) {
      updated.push(status);
    }
  } else {
    const index = updated.indexOf(status);
    if (index > -1) {
      updated.splice(index, 1);
    }
  }
  localSelectedStatuses.value = updated;
};

const togglePriority = (priority, checked) => {
  const updated = [...localSelectedPriorities.value];
  if (checked) {
    if (!updated.includes(priority)) {
      updated.push(priority);
    }
  } else {
    const index = updated.indexOf(priority);
    if (index > -1) {
      updated.splice(index, 1);
    }
  }
  localSelectedPriorities.value = updated;
};

const clearFilters = () => {
  localSearchQuery.value = "";
  localSelectedStatuses.value = [];
  localSelectedPriorities.value = [];
};

const searchInputEl = ref();
const { metaSymbol } = useShortcuts();

defineShortcuts({
  meta_k: {
    usingInput: true,
    handler: () => {
      searchInputEl.value?.focus();
    },
  },
});
</script>
