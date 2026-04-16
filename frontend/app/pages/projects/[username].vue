<template>
  <div class="flex flex-col pb-16 sm:container">
    <template v-if="initialLoading">
      <!-- TabNav Skeleton -->
      <nav
        class="bg-background relative -mx-4 flex gap-x-5 px-4 sm:mx-0 sm:px-0"
      >
        <div v-for="i in 5" :key="`tab-${i}`" class="flex shrink-0 items-center gap-x-1.5 py-3">
          <Skeleton class="size-4 rounded" />
          <Skeleton class="h-4 rounded" :class="[i === 1 ? 'w-16' : i === 2 ? 'w-16' : i === 3 ? 'w-18' : i === 4 ? 'w-14' : 'w-16']" />
        </div>
      </nav>

      <!-- Overview Content Skeleton -->
      <div class="flex flex-col gap-y-6 pt-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
          <Skeleton class="h-7 w-20" />
          <Skeleton class="h-8 w-28 rounded-md" />
        </div>

        <!-- Search & Filter -->
        <div class="flex items-center gap-x-2">
          <Skeleton class="h-9 flex-1 rounded-lg" />
          <Skeleton class="h-9 w-36 rounded-lg" />
        </div>

        <!-- Event List Items Skeleton -->
        <div class="grid grid-cols-1 gap-y-10">
          <div
            v-for="i in 4"
            :key="`event-${i}`"
            class="grid w-full grid-cols-1 items-start gap-4 rounded-lg lg:grid-cols-2"
          >
            <div class="flex items-center gap-x-2.5 sm:gap-x-4">
              <Skeleton class="aspect-4/5 w-26 shrink-0 rounded-md sm:w-40" />
              <div class="flex flex-col gap-y-2">
                <Skeleton class="h-3.5" :class="[i % 2 === 0 ? 'w-20' : 'w-28']" />
                <Skeleton class="h-4" :class="[i % 3 === 0 ? 'w-40 sm:w-52' : 'w-48 sm:w-64']" />
                <div class="flex flex-col gap-y-1.5">
                  <Skeleton class="h-3.5" :class="[i % 2 === 0 ? 'w-36' : 'w-44']" />
                  <Skeleton class="h-3.5" :class="[i % 2 === 0 ? 'w-48' : 'w-40']" />
                </div>
                <div class="mt-1 flex items-center gap-x-2">
                  <Skeleton class="h-8 w-24 rounded-md" />
                  <Skeleton class="h-8 w-20 rounded-md" />
                </div>
              </div>
            </div>
            <div class="grid grow-0 grid-cols-2 gap-2">
              <div
                v-for="j in 3"
                :key="`stat-${i}-${j}`"
                class="flex flex-col items-start gap-y-2 rounded-lg border px-3.5 py-3"
              >
                <Skeleton class="size-8 rounded-lg" />
                <div class="space-y-1">
                  <Skeleton class="h-3.5 w-16" />
                  <Skeleton class="h-3 w-24" />
                </div>
                <Skeleton class="h-7 w-20" />
              </div>
              <Skeleton class="min-h-32 rounded-lg" />
            </div>
          </div>
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
      <template v-if="!isEventPage && !isSettingsPage">
        <TabNav :tabs="projectTabs" />
      </template>

      <div ref="contentArea" :class="isEventPage || isSettingsPage ? '' : 'pt-6'">
        <NuxtPage :project="project" />
      </div>
    </template>
  </div>
</template>

<script setup>
import { Skeleton } from "@/components/ui/skeleton";

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
const isSettingsPage = computed(() => {
  const settingsPath = `/projects/${route.params.username}/settings`;
  return route.path === settingsPath || route.path.startsWith(`${settingsPath}/`);
});

const projectBase = computed(() => `/projects/${route.params.username}`);
const projectTabs = computed(() => [
  { label: "Overview", icon: "hugeicons:dashboard-circle", to: projectBase.value, exact: true },
  { label: "Inquiries", icon: "hugeicons:mail-open-love", to: `${projectBase.value}/inquiries` },
  { label: "Analytics", icon: "hugeicons:analytics-01", to: `${projectBase.value}/analytics` },
  { label: "Activity", icon: "hugeicons:activity-03", to: `${projectBase.value}/activity` },
  { label: "Settings", icon: "hugeicons:settings-01", to: `${projectBase.value}/settings` },
]);

const contentArea = ref(null);
const swipeEnabled = computed(() => !isSettingsPage.value && !isEventPage.value);
useTabSwipe(contentArea, projectTabs, { enabled: swipeEnabled });

provide("project", project);
provide("projectTabs", projectTabs);
</script>
