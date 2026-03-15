<template>
  <div class="flex flex-col gap-y-0">
    <div v-if="eventLoading" class="flex items-center justify-center py-20">
      <div class="flex items-center gap-x-2">
        <Spinner class="size-4 shrink-0" />
        <span class="text-base tracking-tight">Loading event</span>
      </div>
    </div>

    <div v-else-if="eventError" class="flex items-center justify-center py-20">
      <div class="flex flex-col items-center gap-y-4 text-center">
        <div class="space-y-1">
          <h3 class="text-lg font-semibold tracking-tighter">{{ eventError }}</h3>
        </div>
        <NuxtLink
          :to="`/projects/${route.params.username}/events`"
          class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
        >
          <Icon name="lucide:arrow-left" class="size-4 shrink-0" />
          <span>Back to Events</span>
        </NuxtLink>
      </div>
    </div>

    <template v-else>
      <template v-if="!isBrandPage && !isContentPage && !isOperationalPage">
        <TabNav v-if="event" :tabs="eventTabs" />
      </template>

      <div ref="contentArea" :class="isBrandPage || isContentPage || isOperationalPage ? '' : 'pt-6'">
        <NuxtPage :event="event" :project="project" />
      </div>
    </template>
  </div>
</template>

<script setup>
const props = defineProps({
  project: Object,
});

const route = useRoute();

const {
  data: eventResponse,
  pending: eventLoading,
  error: fetchError,
} = await useLazySanctumFetch(
  () => `/api/projects/${route.params.username}/events/${route.params.eventSlug}`,
  {
    key: `event-${route.params.username}-${route.params.eventSlug}`,
  }
);

const event = computed(() => eventResponse.value?.data || null);

const eventError = computed(() => {
  if (!fetchError.value) return null;

  const err = fetchError.value;
  if (err.statusCode === 404) return "Event not found";
  if (err.statusCode === 403) return "You do not have permission to view this event";
  return err.message || "Failed to load event";
});

usePageMeta(null, {
  title: computed(() => event.value?.title || "Event"),
});

const isBrandPage = computed(() => !!route.params.brandSlug);
const isContentPage = computed(() => {
  const contentPath = `/projects/${route.params.username}/events/${route.params.eventSlug}/content`;
  return route.path === contentPath || route.path.startsWith(`${contentPath}/`);
});
const isOperationalPage = computed(() => {
  const opPath = `/projects/${route.params.username}/events/${route.params.eventSlug}/operational`;
  return route.path === opPath || route.path.startsWith(`${opPath}/`);
});

// Share event data to AppHeader via useState
const headerEvent = useState("header-event", () => null);
watch(
  event,
  (val) => {
    headerEvent.value = val;
  },
  { immediate: true }
);
onBeforeUnmount(() => {
  headerEvent.value = null;
});

const eventBase = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}`
);
const eventTabs = computed(() => [
  { label: "Overview", icon: "hugeicons:dashboard-circle", to: eventBase.value, exact: true },
  { label: "Brands", icon: "hugeicons:store-02", to: `${eventBase.value}/brands` },
  { label: "Operational", icon: "hugeicons:briefcase-01", to: `${eventBase.value}/operational/orders`, activeFor: [`${eventBase.value}/operational`] },
  { label: "Content", icon: "hugeicons:note-01", to: `${eventBase.value}/content/rundown` },
]);

const contentArea = ref(null);
const swipeEnabled = computed(() => !isOperationalPage.value && !isContentPage.value && !isBrandPage.value);
useTabSwipe(contentArea, eventTabs, { enabled: swipeEnabled });

provide("event", event);
provide("eventTabs", eventTabs);
</script>
