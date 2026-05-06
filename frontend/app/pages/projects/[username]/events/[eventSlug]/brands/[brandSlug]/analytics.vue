<template>
  <ClientOnly>
    <div v-if="!brandEventId" class="flex items-center justify-center py-12">
      <Spinner class="size-4 shrink-0" />
    </div>

    <div v-else class="flex flex-col gap-y-4">
      <div v-if="editions.length > 1" class="flex items-center justify-end gap-x-2">
        <span class="text-muted-foreground text-sm tracking-tight">Edition</span>
        <select
          v-model="selectedEditionId"
          @change="onEditionChange"
          class="border-border bg-background hover:bg-muted rounded-md border px-2.5 py-1.5 text-sm tracking-tight"
        >
          <option
            v-for="be in editions"
            :key="be.id"
            :value="be.id"
          >
            {{ be.event?.title || `Edition ${be.event?.edition_number}` }}
          </option>
        </select>
      </div>

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
  </ClientOnly>
</template>

<script setup>
const props = defineProps({ brandEvent: Object });
const route = useRoute();

const selectedPeriod = ref(7);
const brandEventId = computed(() => props.brandEvent?.id);
const selectedEditionId = ref(null);

const brandAsUser = computed(() =>
  props.brandEvent
    ? {
        id: props.brandEvent.brand?.id,
        name: props.brandEvent.brand?.name || props.brandEvent.brand?.brand_name,
        username: props.brandEvent.brand?.slug,
      }
    : null,
);

const { data: brandData } = await useLazyFetch(
  () => `/api/brands/${route.params.brandSlug}`,
  {
    baseURL: useRuntimeConfig().public.apiUrl,
    key: `brand-profile-edition-switcher-${route.params.brandSlug}`,
    credentials: "include",
    server: false,
  },
);

const editions = computed(() => brandData.value?.data?.brand_events || []);

watch(
  [brandEventId, editions],
  ([id, list]) => {
    if (id && list.length) {
      const match = list.find((be) => be.id === id);
      selectedEditionId.value = match?.id || id;
    }
  },
  { immediate: true },
);

const onEditionChange = () => {
  const target = editions.value.find((be) => be.id === selectedEditionId.value);
  if (!target?.event?.slug) return;
  if (target.id === brandEventId.value) return;
  navigateTo(
    `/projects/${route.params.username}/events/${target.event.slug}/brands/${route.params.brandSlug}/analytics`,
  );
};

const {
  data: visitsData,
  status: visitsStatus,
  error: visitsError,
  execute: executeVisits,
} = await useLazyFetch(
  () => {
    if (!brandEventId.value) return null;
    return `/api/analytics/visits?type=brand_event&id=${brandEventId.value}&days=${selectedPeriod.value}`;
  },
  {
    baseURL: useRuntimeConfig().public.apiUrl,
    key: `analytics-visits-brand-event-${brandEventId.value}-${selectedPeriod.value}`,
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
    if (!brandEventId.value) return null;
    return `/api/analytics/clicks?type=brand_event&id=${brandEventId.value}&days=${selectedPeriod.value}`;
  },
  {
    baseURL: useRuntimeConfig().public.apiUrl,
    key: `analytics-clicks-brand-event-${brandEventId.value}-${selectedPeriod.value}`,
    credentials: "include",
    immediate: false,
    transform: (response) => response.data,
    server: false,
  },
);

watch(
  [brandEventId, selectedPeriod],
  ([id]) => {
    if (id) {
      executeVisits();
      executeClicks();
    }
  },
  { immediate: true },
);

const analyticsLoading = computed(
  () => visitsStatus.value === "pending" || clicksStatus.value === "pending",
);
</script>
