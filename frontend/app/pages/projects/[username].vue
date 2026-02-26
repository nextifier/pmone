<template>
  <div class="flex flex-col pb-16 sm:container">
    <template v-if="initialLoading">
      <div class="flex items-center justify-center py-20">
        <div class="flex items-center gap-x-2">
          <Spinner class="size-4 shrink-0" />
          <span class="text-base tracking-tight">Loading</span>
        </div>
      </div>
    </template>

    <template v-else-if="error">
      <div class="flex items-center justify-center py-20">
        <div class="flex flex-col items-center gap-y-4 text-center">
          <div class="space-y-1">
            <h3 class="text-lg font-semibold tracking-tighter">{{ error }}</h3>
          </div>
          <NuxtLink
            to="/projects"
            class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
          >
            <Icon name="lucide:arrow-left" class="size-4 shrink-0" />
            <span>Back to Projects</span>
          </NuxtLink>
        </div>
      </div>
    </template>

    <template v-else-if="project">
      <template v-if="!isEventPage">
        <TabNav :tabs="projectTabs" />
      </template>

      <div :class="isEventPage ? '' : 'pt-6'">
        <NuxtPage :project="project" />
      </div>
    </template>
  </div>
</template>

<script setup>
definePageMeta({
  layout: "app",
  middleware: ["sanctum:auth"],
});

const route = useRoute();

const {
  data: projectResponse,
  pending: initialLoading,
  error: projectError,
} = await useLazySanctumFetch(() => `/api/projects/${route.params.username}`, {
  key: `project-dashboard-${route.params.username}`,
});

const project = computed(() => projectResponse.value?.data || null);

// Share project data to AppHeader via useState
const headerProject = useState("header-project", () => null);
watch(
  project,
  (val) => {
    headerProject.value = val;
  },
  { immediate: true }
);
onBeforeUnmount(() => {
  headerProject.value = null;
});

const error = computed(() => {
  if (!projectError.value) return null;

  const err = projectError.value;
  if (err.statusCode === 404) {
    return "Project not found";
  } else if (err.statusCode === 403) {
    return "You do not have permission to view this project";
  }
  return err.message || "Failed to load project";
});

usePageMeta(null, {
  title: computed(() => project.value?.name || "Project"),
});

const isEventPage = computed(() => !!route.params.eventSlug);

const projectBase = computed(() => `/projects/${route.params.username}`);
const projectTabs = computed(() => [
  { label: "Overview", to: projectBase.value, exact: true },
  { label: "Events", to: `${projectBase.value}/events` },
  { label: "Inquiries", to: `${projectBase.value}/inquiries` },
  { label: "Members", to: `${projectBase.value}/members` },
  { label: "Analytics", to: `${projectBase.value}/analytics` },
  { label: "Activity", to: `${projectBase.value}/activity` },
  { label: "Settings", to: `${projectBase.value}/settings` },
]);

provide("project", project);
</script>
