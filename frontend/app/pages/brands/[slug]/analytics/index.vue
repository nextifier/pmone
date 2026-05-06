<template>
  <ClientOnly>
    <AnalyticsView
      :user="brandAsUser"
      :loading="status === 'pending' || analyticsLoading"
      :error="error || visitsError || clicksError"
      :visits-data="visitsData"
      :clicks-data="clicksData"
      v-model:selected-period="selectedPeriod"
      :back-destination="`/brands/${slug}`"
      :per-event-breakdown="visitsData?.per_event_breakdown"
      :per-event-link-path="`/brands/${slug}/analytics/{brand_event_id}`"
    />
  </ClientOnly>
</template>

<script setup>
definePageMeta({
  ssr: false,
  middleware: ["sanctum:auth"],
  layout: "app",
});

const route = useRoute();
const slug = computed(() => route.params.slug);
const { user: authUser } = useSanctumAuth();
const { hasPermission, hasAnyRole } = usePermission();

const selectedPeriod = ref(7);

const {
  data: brandData,
  status,
  error: fetchError,
} = await useLazyFetch(() => `/api/brands/${slug.value}`, {
  baseURL: useRuntimeConfig().public.apiUrl,
  key: `brand-profile-${slug.value}`,
  credentials: "include",
  server: false,
});

const brand = computed(() => brandData.value?.data || null);

const brandAsUser = computed(() =>
  brand.value
    ? {
        id: brand.value.id,
        name: brand.value.name,
        username: brand.value.slug,
      }
    : null,
);

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

const canViewAnalytics = computed(() => {
  if (!authUser.value || !brand.value) return false;

  const isMember = (brand.value.members || []).some(
    (m) => m.id === authUser.value.id,
  );
  if (isMember) return true;

  if (hasAnyRole(["master", "admin"])) return true;

  return hasPermission("analytics.view");
});

watch(
  [authUser, brand],
  ([_, newBrand]) => {
    if (newBrand && !canViewAnalytics.value) {
      navigateTo(`/brands/${slug.value}`);
    }
  },
  { immediate: true },
);

const {
  data: visitsData,
  status: visitsStatus,
  error: visitsError,
  execute: executeVisits,
} = await useLazyFetch(
  () => {
    if (!brand.value?.id) return null;
    return `/api/analytics/visits?type=brand&id=${brand.value.id}&days=${selectedPeriod.value}`;
  },
  {
    baseURL: useRuntimeConfig().public.apiUrl,
    key: `analytics-visits-brand-${brand.value?.id}-${selectedPeriod.value}`,
    credentials: "include",
    immediate: false,
    transform: (response) => response.data,
    server: false,
  },
);

const {
  data: clicksData,
  status: clicksStatus,
  error: clicksError,
  execute: executeClicks,
} = await useLazyFetch(
  () => {
    if (!brand.value?.id) return null;
    return `/api/analytics/clicks?type=brand&id=${brand.value.id}&days=${selectedPeriod.value}`;
  },
  {
    baseURL: useRuntimeConfig().public.apiUrl,
    key: `analytics-clicks-brand-${brand.value?.id}-${selectedPeriod.value}`,
    credentials: "include",
    immediate: false,
    transform: (response) => response.data,
    server: false,
  },
);

watch(
  [brand, selectedPeriod, canViewAnalytics],
  ([newBrand, _, canView]) => {
    if (newBrand?.id && canView) {
      executeVisits();
      executeClicks();
    }
  },
  { immediate: true },
);

const analyticsLoading = computed(() => {
  if (!brand.value || !canViewAnalytics.value) return false;
  return visitsStatus.value === "pending" || clicksStatus.value === "pending";
});

usePageMeta(null, {
  title: computed(() => `Analytics · ${brand.value?.name || ""}`),
});
</script>
