<template>
  <div class="grid gap-y-3">
    <div class="flex h-9 gap-x-2">
      <div class="relative flex h-full grow items-center">
        <Icon
          name="lucide:search"
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
          <Icon name="lucide:x" class="size-3 shrink-0" />
        </button>
      </div>

      <Popover>
        <PopoverTrigger asChild>
          <button
            class="hover:bg-muted relative flex h-9 shrink-0 items-center justify-center gap-x-1.5 rounded-md border px-3 text-sm tracking-tight active:scale-98"
          >
            <Icon name="lucide:list-filter" class="size-4 shrink-0" />
            <span>Filter</span>
            <span
              v-if="localSelectedStatuses.length > 0"
              class="bg-primary text-primary-foreground squircle absolute top-0 right-0 inline-flex size-4 translate-x-1/2 -translate-y-1/2 items-center justify-center text-[11px] font-medium tracking-tight"
            >
              {{ localSelectedStatuses.length }}
            </span>
          </button>
        </PopoverTrigger>
        <PopoverContent class="w-48 p-3" align="end">
          <div class="space-y-3">
            <div class="text-muted-foreground text-xs font-medium">Status</div>
            <div class="space-y-2">
              <div
                v-for="status in statusOptions"
                :key="status"
                class="flex items-center gap-2"
              >
                <Checkbox
                  :id="`status-${status}`"
                  :model-value="localSelectedStatuses.includes(status)"
                  @update:model-value="(checked) => toggleStatus(status, checked)"
                />
                <Label
                  :for="`status-${status}`"
                  class="grow cursor-pointer font-normal tracking-tight capitalize"
                >
                  {{ status }}
                </Label>
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
        <Icon name="lucide:x" class="size-4 shrink-0" />
        <span class="hidden sm:flex">Clear filters</span>
      </button>

      <button
        @click="$emit('refresh')"
        :disabled="pending"
        class="hover:bg-muted flex aspect-square h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border text-sm tracking-tight active:scale-98 sm:aspect-auto sm:px-2.5"
      >
        <Icon
          name="lucide:refresh-cw"
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
  statusOptions: {
    type: Array,
    default: () => ["active", "draft", "archived"],
  },
  pending: {
    type: Boolean,
    default: false,
  },
  searchPlaceholder: {
    type: String,
    default: "Search projects",
  },
});

const emit = defineEmits(["update:searchQuery", "update:selectedStatuses", "refresh"]);

const localSearchQuery = computed({
  get: () => props.searchQuery,
  set: (value) => emit("update:searchQuery", value),
});

const localSelectedStatuses = computed({
  get: () => props.selectedStatuses,
  set: (value) => emit("update:selectedStatuses", value),
});

const hasActiveFilters = computed(() => {
  return localSearchQuery.value !== "" || localSelectedStatuses.value.length > 0;
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

const clearFilters = () => {
  localSearchQuery.value = "";
  localSelectedStatuses.value = [];
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
