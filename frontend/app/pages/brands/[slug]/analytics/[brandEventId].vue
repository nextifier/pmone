<template>
  <ClientOnly>
    <div class="min-h-screen-offset pt-4 pb-16">
      <div v-if="status === 'pending'" class="min-h-screen-offset flex items-center justify-center">
        <div class="flex items-center gap-x-2">
          <Spinner class="size-4 shrink-0" />
          <span class="text-base tracking-tight">Loading analytics</span>
        </div>
      </div>

      <ErrorState v-else-if="error" :error="error" />

      <template v-else-if="brand">
        <div class="flex flex-col gap-y-6">
          <div class="flex items-center justify-between gap-x-3">
            <ButtonBack :destination="`/brands/${slug}`" />

            <div class="flex items-center gap-x-2">
              <span class="text-muted-foreground text-sm tracking-tight">Edition</span>
              <Select
                v-model="selectedBrandEventId"
                @update:model-value="onEditionChange"
              >
                <SelectTrigger class="h-9 w-[220px]">
                  <SelectValue placeholder="Select edition" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Events (Global)</SelectItem>
                  <SelectItem
                    v-for="be in editions"
                    :key="be.id"
                    :value="String(be.id)"
                  >
                    {{ be.event?.title || `Edition ${be.event?.edition_number}` }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <h1 class="page-title">{{ pageTitle }}</h1>

          <AnalyticsView
            :user="brandAsUser"
            :loading="analyticsLoading"
            :error="visitsError || clicksError"
            :visits-data="visitsData"
            :clicks-data="clicksData"
            v-model:selected-period="selectedPeriod"
            embedded
          />
        </div>
      </template>
    </div>
  </ClientOnly>
</template>

<script setup>
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

definePageMeta({
  ssr: false,
  middleware: ["sanctum:auth"],
  layout: "app",
});

const route = useRoute();
const slug = computed(() => route.params.slug);
const brandEventIdParam = computed(() => Number(route.params.brandEventId));
const { user: authUser } = useSanctumAuth();
const { hasPermission, hasAnyRole } = usePermission();

const selectedPeriod = ref(7);
// shadcn-vue Select model is a string; coerce route param so the binding
// stays consistent between the initial render and user-triggered changes.
const selectedBrandEventId = ref(String(brandEventIdParam.value));

watch(brandEventIdParam, (val) => {
  selectedBrandEventId.value = String(val);
});

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
const editions = computed(() => brand.value?.brand_events || []);

const currentEdition = computed(() =>
  editions.value.find((be) => be.id === brandEventIdParam.value),
);

const brandAsUser = computed(() =>
  brand.value
    ? {
        id: brand.value.id,
        name: brand.value.name,
        username: brand.value.slug,
      }
    : null,
);

const pageTitle = computed(() => {
  const eventTitle =
    currentEdition.value?.event?.title ||
    (currentEdition.value?.event?.edition_number
      ? `Edition ${currentEdition.value.event.edition_number}`
      : "Edition");
  return `${eventTitle} · ${brand.value?.name || ""}`;
});

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
  [authUser, brand, brandEventIdParam],
  ([_, newBrand, beId]) => {
    if (!newBrand) return;
    if (!canViewAnalytics.value) {
      navigateTo(`/brands/${slug.value}`);
      return;
    }
    const validBrandEvent = (newBrand.brand_events || []).some(
      (be) => be.id === beId,
    );
    if (!validBrandEvent) {
      navigateTo(`/brands/${slug.value}/analytics`);
    }
  },
  { immediate: true },
);

const onEditionChange = () => {
  if (selectedBrandEventId.value === "all") {
    navigateTo(`/brands/${slug.value}/analytics`);
    return;
  }
  // Select emits strings, the route param is parsed to a Number — compare
  // loose-equality after coercion so we don't loop-navigate.
  if (Number(selectedBrandEventId.value) !== brandEventIdParam.value) {
    navigateTo(`/brands/${slug.value}/analytics/${selectedBrandEventId.value}`);
  }
};

const {
  data: visitsData,
  status: visitsStatus,
  error: visitsError,
  execute: executeVisits,
} = await useLazyFetch(
  () => {
    if (!brandEventIdParam.value) return null;
    return `/api/analytics/visits?type=brand_event&id=${brandEventIdParam.value}&days=${selectedPeriod.value}`;
  },
  {
    baseURL: useRuntimeConfig().public.apiUrl,
    key: `analytics-visits-brand-event-${brandEventIdParam.value}-${selectedPeriod.value}`,
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
    if (!brandEventIdParam.value) return null;
    return `/api/analytics/clicks?type=brand_event&id=${brandEventIdParam.value}&days=${selectedPeriod.value}`;
  },
  {
    baseURL: useRuntimeConfig().public.apiUrl,
    key: `analytics-clicks-brand-event-${brandEventIdParam.value}-${selectedPeriod.value}`,
    credentials: "include",
    immediate: false,
    transform: (response) => response.data,
    server: false,
  },
);

watch(
  [brand, brandEventIdParam, selectedPeriod, canViewAnalytics],
  ([newBrand, beId, _, canView]) => {
    if (newBrand?.id && beId && canView) {
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
  title: computed(() => `Analytics · ${pageTitle.value}`),
});
</script>
