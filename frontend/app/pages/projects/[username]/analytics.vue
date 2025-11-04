<template>
  <AnalyticsView
    :username="username"
    :loading="status === 'pending'"
    :error="error"
    :visits-data="visitsData"
    :clicks-data="clicksData"
    v-model:selected-period="selectedPeriod"
    :back-destination="`/p/${username}`"
  />
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const route = useRoute();
const username = computed(() => route.params.username);
const { user: authUser } = useSanctumAuth();

const selectedPeriod = ref(7);

// Fetch project profile
const {
  data: projectData,
  status,
  error: fetchError,
} = await useFetch(() => `/api/p/${username.value}`, {
  baseURL: useRuntimeConfig().public.apiUrl,
  key: `project-profile-${username.value}`,
});

const project = computed(() => projectData.value?.data || null);

const error = computed(() => {
  if (!fetchError.value) return null;

  const err = fetchError.value;
  return {
    statusCode: err.statusCode || 500,
    statusMessage: err.data?.message || err.statusMessage || "Error",
    message: err.data?.message || err.message || "Failed to load analytics",
    stack: err.stack,
  };
});

// Check authorization
const canViewAnalytics = computed(() => {
  if (!authUser.value || !project.value) return false;

  // User can view if they are the project owner
  if (authUser.value.id === project.value.user_id) return true;

  // Master or admin can view all analytics
  const userRoles = authUser.value.roles || [];
  return userRoles.some((role) => ["master", "admin"].includes(role));
});

// Redirect if unauthorized
watch(
  [authUser, project],
  ([newAuthUser, newProject]) => {
    if (newProject && !canViewAnalytics.value) {
      navigateTo(`/p/${username.value}`);
    }
  },
  { immediate: true }
);

// Fetch visits data
const { data: visitsData } = await useFetch(
  () => `/api/analytics/visits?type=project&id=${project.value?.id}&days=${selectedPeriod.value}`,
  {
    baseURL: useRuntimeConfig().public.apiUrl,
    key: `analytics-visits-${project.value?.id}-${selectedPeriod.value}`,
    credentials: "include",
    watch: [selectedPeriod],
    immediate: computed(() => !!project.value?.id && canViewAnalytics.value),
    transform: (response) => response.data,
    server: false,
  }
);

// Fetch clicks data
const { data: clicksData } = await useFetch(
  () => `/api/analytics/clicks?type=project&id=${project.value?.id}&days=${selectedPeriod.value}`,
  {
    baseURL: useRuntimeConfig().public.apiUrl,
    key: `analytics-clicks-${project.value?.id}-${selectedPeriod.value}`,
    credentials: "include",
    watch: [selectedPeriod],
    immediate: computed(() => !!project.value?.id && canViewAnalytics.value),
    transform: (response) => response.data,
    server: false,
  }
);

usePageMeta("", {
  title: `Analytics - @${username.value}`,
  description: `View analytics for @${username.value}`,
});
</script>
