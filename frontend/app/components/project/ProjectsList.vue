<template>
  <!-- Error State -->
  <div
    v-if="error"
    class="border-destructive/50 bg-destructive/10 flex flex-col items-start gap-y-3 rounded-lg border p-4"
  >
    <div class="text-destructive flex items-center gap-x-2">
      <Icon name="hugeicons:alert-circle" class="size-5" />
      <span class="font-medium tracking-tight">{{ errorTitle }}</span>
    </div>
    <p class="text-sm tracking-tight">
      {{ error?.message || "An error occurred while fetching data." }}
    </p>
  </div>

  <!-- Loading State -->
  <LoadingState v-else-if="isInitialLoading" label="Loading projects.." />

  <!-- Empty State -->
  <div
    v-else-if="projects.length === 0"
    class="flex flex-col items-center gap-4 rounded-lg border border-dashed py-12 text-center"
  >
    <div
      class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
    >
      <div class="translate-y-1.5 -rotate-6">
        <Icon name="hugeicons:file-empty-01" />
      </div>
      <div>
        <Icon name="hugeicons:search-remove" />
      </div>
      <div class="translate-y-1.5 rotate-6">
        <Icon name="hugeicons:user" />
      </div>
    </div>
    <div class="space-y-1">
      <h3 class="text-lg font-semibold tracking-tighter">{{ emptyTitle }}</h3>
      <p class="text-muted-foreground text-sm tracking-tight">
        {{ emptyDescription }}
      </p>
    </div>
    <slot name="empty-actions" />
  </div>

  <!-- Projects List -->
  <div v-else ref="projectsListEl" class="divide-border frame divide-y border">
    <div
      v-for="project in projects"
      :key="project.id"
      :data-id="project.id"
      class="hover:bg-muted/50 bg-background relative isolate flex items-center gap-x-1 px-3 py-4 first:rounded-t-xl last:rounded-b-xl sm:gap-x-2"
    >
      <NuxtLink
        v-if="!isTrash"
        :to="`/projects/${project.username}`"
        class="absolute inset-0 z-10"
      />

      <!-- Drag Handle (only for non-trash, non-filtered lists) -->
      <div
        v-if="!isTrash && enableDragDrop"
        class="hover:bg-muted text-muted-foreground hover:text-primary relative z-20 -ml-1.5 flex size-8 shrink-0 items-center justify-center rounded-md transition-colors sm:-mx-1"
        :class="
          hasActiveFilters
            ? 'cursor-not-allowed opacity-30'
            : 'drag-handle cursor-grab active:cursor-grabbing'
        "
      >
        <Icon name="lucide:grip-vertical" class="size-4.5" />
      </div>

      <div class="flex w-full items-center gap-x-1.5 sm:gap-x-2">
        <Avatar :model="project" class="squircle size-12 overflow-hidden" />

        <div class="flex grow flex-col gap-y-1.5">
          <div class="flex items-center gap-x-2">
            <h3 class="line-clamp-1 text-sm font-semibold tracking-tight">{{ project.name }}</h3>
          </div>

          <div class="text-muted-foreground flex items-center gap-x-3 text-xs tracking-tight">
            <span v-if="project.members?.length" class="flex items-center gap-x-1">
              <div class="relative z-20 flex -space-x-1.5">
                <Avatar
                  v-for="member in project.members.slice(0, 4)"
                  :model="member"
                  :key="member.id"
                  class="!bg-border ring-background [&_.initial]:text-muted-foreground size-6 shrink-0 overflow-hidden !rounded-full ring-1 [&_.initial]:text-[10px] [&_.initial]:font-medium"
                  v-tippy="member.name"
                />
                <span
                  v-if="project.members_count && project.members_count > 4"
                  class="ring-background bg-border text-muted-foreground relative flex size-6 shrink-0 items-center justify-center overflow-hidden rounded-full border text-center text-[10px] font-medium tracking-tighter ring-1"
                  >+{{ project.members_count - 4 }}</span
                >
              </div>
              <span v-if="project.members_count"
                >{{ project.members_count }} member{{ project.members_count > 1 ? "s" : "" }}</span
              >
            </span>
          </div>
        </div>
      </div>

      <div class="mt-2 flex shrink-0 flex-col items-end gap-y-1.5">
        <span
          class="flex items-center gap-x-1 rounded-full px-2 py-0.5 text-xs font-medium tracking-tight capitalize"
          :class="{
            'bg-success/10 text-success-foreground': project.status === 'active',
            'bg-warning/10 text-warning-foreground': project.status === 'draft',
            'bg-muted text-muted-foreground': project.status === 'archived',
          }"
        >
          <span
            class="size-1.5 rounded-full"
            :class="{
              'bg-success': project.status === 'active',
              'bg-warning': project.status === 'draft',
              'bg-muted-foreground': project.status === 'archived',
            }"
          ></span>
          {{ project.status }}
        </span>

        <Popover
          :open="openPopoverId[project.id] ?? false"
          @update:open="(val) => (openPopoverId[project.id] = val)"
        >
          <PopoverTrigger asChild>
            <button
              class="hover:bg-muted data-[state=open]:bg-muted text-muted-foreground hover:text-foreground data-[state=open]:text-foreground relative z-20 inline-flex size-8 items-center justify-center rounded-md"
            >
              <Icon name="lucide:ellipsis" class="size-4" />
            </button>
          </PopoverTrigger>
          <PopoverContent align="end" class="w-48 p-1">
            <slot name="row-actions" :project="project" />
          </PopoverContent>
        </Popover>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";

const props = defineProps({
  projects: {
    type: Array,
    required: true,
  },
  pending: {
    type: Boolean,
    default: false,
  },
  error: {
    type: [Object, null],
    default: null,
  },
  errorTitle: {
    type: String,
    default: "Error loading projects",
  },
  emptyTitle: {
    type: String,
    default: "No projects found",
  },
  emptyDescription: {
    type: String,
    default: "Get started by creating a new project.",
  },
  enableDragDrop: {
    type: Boolean,
    default: true,
  },
  hasActiveFilters: {
    type: Boolean,
    default: false,
  },
  isTrash: {
    type: Boolean,
    default: false,
  },
});

const isInitialLoading = computed(() => props.pending && props.projects.length === 0);

const projectsListEl = ref(null);

// Track open popover state by project ID
const openPopoverId = ref({});

// Close all popovers on route change (handles keepalive navigation)
const route = useRoute();
watch(
  () => route.fullPath,
  () => {
    openPopoverId.value = {};
  }
);

// Also close popovers when component is deactivated (keepalive)
onDeactivated(() => {
  openPopoverId.value = {};
});

defineExpose({
  projectsListEl,
});
</script>
