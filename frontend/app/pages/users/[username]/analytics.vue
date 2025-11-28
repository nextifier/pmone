<template>
  <AnalyticsView
    :user="user"
    :loading="status === 'pending'"
    :error="error"
    :visits-data="visitsData"
    :clicks-data="clicksData"
    v-model:selected-period="selectedPeriod"
    :back-destination="`/users/${username}`"
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

// Fetch user profile with lazy loading
const {
  data: userData,
  status,
  error: fetchError,
} = await useLazyFetch(() => `/api/${username.value}`, {
  baseURL: useRuntimeConfig().public.apiUrl,
  key: `user-profile-${username.value}`,
  server: false,
});

const user = computed(() => userData.value?.data || null);

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
  if (!authUser.value || !user.value) return false;

  // User can view their own analytics
  if (authUser.value.id === user.value.id) return true;

  // Master or admin can view all analytics
  const userRoles = authUser.value.roles || [];
  return userRoles.some((role) => ["master", "admin"].includes(role));
});

// Redirect if unauthorized
watch(
  [authUser, user],
  ([newAuthUser, newUser]) => {
    if (newUser && !canViewAnalytics.value) {
      navigateTo(`/users/${username.value}`);
    }
  },
  { immediate: true }
);

// Fetch visits data with lazy loading
const { data: visitsData } = await useLazyFetch(
  () => `/api/analytics/visits?type=user&id=${user.value?.id}&days=${selectedPeriod.value}`,
  {
    baseURL: useRuntimeConfig().public.apiUrl,
    key: `analytics-visits-${user.value?.id}-${selectedPeriod.value}`,
    credentials: "include",
    watch: [selectedPeriod],
    immediate: computed(() => !!user.value?.id && canViewAnalytics.value),
    transform: (response) => response.data,
    server: false,
  }
);

// Fetch clicks data with lazy loading
const { data: clicksData } = await useLazyFetch(
  () => `/api/analytics/clicks?type=user&id=${user.value?.id}&days=${selectedPeriod.value}`,
  {
    baseURL: useRuntimeConfig().public.apiUrl,
    key: `analytics-clicks-${user.value?.id}-${selectedPeriod.value}`,
    credentials: "include",
    watch: [selectedPeriod],
    immediate: computed(() => !!user.value?.id && canViewAnalytics.value),
    transform: (response) => response.data,
    server: false,
  }
);

usePageMeta("", {
  title: `Analytics - @${username.value}`,
  description: `View analytics for @${username.value}`,
});
</script>
