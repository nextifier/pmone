<template>
  <div class="flex flex-col gap-y-0">
    <div v-if="eventLoading" class="flex flex-col gap-y-0">
      <!-- TabNav Skeleton -->
      <nav class="bg-background relative -mx-4 flex gap-x-5 px-4 sm:mx-0 sm:px-0">
        <div v-for="i in 4" :key="`tab-${i}`" class="flex shrink-0 items-center gap-x-1.5 py-3">
          <Skeleton class="size-4 rounded" />
          <Skeleton class="h-4 rounded" :class="[i === 1 ? 'w-16' : i === 2 ? 'w-14' : i === 3 ? 'w-20' : 'w-14']" />
        </div>
      </nav>

      <!-- Overview Content Skeleton -->
      <div class="flex flex-col gap-y-6 pt-6 lg:gap-y-10">
        <!-- Hero: Poster + Event Info -->
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
          <!-- Poster -->
          <Skeleton class="aspect-4/5 w-full shrink-0 rounded-xl sm:w-40 lg:w-48" />

          <!-- Event Info -->
          <div class="flex flex-col items-start gap-y-2 sm:pt-3">
            <!-- Project Context -->
            <div class="flex items-center gap-x-1.5">
              <Skeleton class="h-3.5 w-24" />
              <Skeleton class="h-3.5 w-3" />
              <Skeleton class="h-3.5 w-20" />
            </div>

            <!-- Title -->
            <Skeleton class="h-7 w-64 sm:h-8 sm:w-80" />

            <!-- Badges -->
            <div class="flex flex-wrap items-center gap-1.5">
              <Skeleton class="h-6 w-18 rounded-full" />
              <Skeleton class="h-6 w-14 rounded-full" />
              <Skeleton class="h-6 w-24 rounded-full" />
            </div>

            <!-- Metadata Grid -->
            <div class="mt-2 flex flex-col gap-x-6 gap-y-4 sm:flex-row sm:flex-wrap">
              <div v-for="i in 3" :key="`meta-${i}`" class="flex items-center gap-x-2">
                <Skeleton class="size-8 shrink-0 rounded-lg" />
                <div class="flex flex-col gap-y-1">
                  <Skeleton class="h-3.5" :class="[i === 1 ? 'w-10' : i === 2 ? 'w-10' : 'w-16']" />
                  <Skeleton class="h-4" :class="[i === 1 ? 'w-36' : i === 2 ? 'w-24' : 'w-32']" />
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-3 flex items-center gap-2">
              <Skeleton class="h-8 w-28 rounded-md" />
              <Skeleton class="h-8 w-20 rounded-md" />
            </div>
          </div>
        </div>

        <!-- Stats Grid Skeleton -->
        <div class="grid grow-0 grid-cols-2 gap-2 sm:grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
          <!-- Stat Cards (Exhibitors, Orders, Revenue) -->
          <div
            v-for="i in 3"
            :key="`stat-${i}`"
            class="bg-card flex flex-col items-start gap-y-2 rounded-xl border px-3 py-4 sm:px-4 sm:py-5"
          >
            <Skeleton class="size-5 rounded" />
            <div class="space-y-1">
              <Skeleton class="h-3.5" :class="[i === 1 ? 'w-18' : i === 2 ? 'w-14' : 'w-16']" />
              <Skeleton class="h-3" :class="[i === 1 ? 'w-28' : i === 2 ? 'w-32' : 'w-28']" />
            </div>
            <Skeleton class="h-7 w-12" />
          </div>

          <!-- Nav Cards (Operational, Content) -->
          <div
            v-for="i in 2"
            :key="`nav-${i}`"
            class="bg-card flex flex-col gap-y-2 rounded-xl border px-4 py-5"
          >
            <Skeleton class="size-5 rounded" />
            <div class="space-y-1">
              <Skeleton class="h-3.5" :class="[i === 1 ? 'w-20' : 'w-14']" />
              <Skeleton class="h-3" :class="[i === 1 ? 'w-44' : 'w-40']" />
            </div>
          </div>

          <!-- Chart -->
          <Skeleton class="aspect-square w-full rounded-xl" />
        </div>
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
import { Skeleton } from "@/components/ui/skeleton";

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
const { hasPermission } = usePermission();

const eventTabs = computed(() => {
  const tabs = [
    { label: "Overview", icon: "hugeicons:dashboard-circle", to: eventBase.value, exact: true },
    { label: "Brands", icon: "hugeicons:store-02", to: `${eventBase.value}/brands` },
    { label: "Operational", icon: "hugeicons:briefcase-01", to: `${eventBase.value}/operational/orders`, activeFor: [`${eventBase.value}/operational`] },
    { label: "Content", icon: "hugeicons:note-01", to: `${eventBase.value}/content/rundown` },
  ];

  if (hasPermission("hotels.read")) {
    tabs.push({ label: "Hotels", icon: "hugeicons:building-01", to: `${eventBase.value}/hotels` });
  }
  if (hasPermission("reservations.read")) {
    tabs.push({ label: "Reservations", icon: "hugeicons:calendar-02", to: `${eventBase.value}/reservations` });
  }

  return tabs;
});

const contentArea = ref(null);
const swipeEnabled = computed(() => !isOperationalPage.value && !isContentPage.value && !isBrandPage.value);
useTabSwipe(contentArea, eventTabs, { enabled: swipeEnabled });

provide("event", event);
provide("eventTabs", eventTabs);
</script>
