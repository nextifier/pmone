<template>
  <div
    class="mx-auto flex flex-col gap-y-6 pb-16 lg:max-w-4xl xl:max-w-6xl"
  >
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h3 class="page-title">Events</h3>
      <NuxtLink
        :to="`/projects/${route.params.username}/events/create`"
        class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-x-1.5 rounded-lg px-3.5 py-2 text-sm font-medium tracking-tight transition active:scale-98"
      >
        <Icon name="hugeicons:add-01" class="size-4" />
        <span>Create Event</span>
      </NuxtLink>
    </div>

    <!-- Search & Filter -->
    <div class="flex items-center gap-x-2">
      <div class="relative flex-1">
        <Icon
          name="hugeicons:search-01"
          class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
        />
        <input
          v-model="search"
          type="text"
          placeholder="Search events..."
          class="border-border bg-background placeholder:text-muted-foreground h-9 w-full rounded-lg border py-1 pr-3 pl-9 text-sm tracking-tight focus:outline-none"
        />
      </div>

      <Select v-model="statusFilter">
        <SelectTrigger class="w-36">
          <SelectValue placeholder="All Status" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All Status</SelectItem>
          <SelectItem value="draft">Draft</SelectItem>
          <SelectItem value="published">Published</SelectItem>
          <SelectItem value="archived">Archived</SelectItem>
          <SelectItem value="cancelled">Cancelled</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <!-- Loading -->
    <template v-if="loading">
      <div class="divide-border grid grid-cols-1 divide-y border-y">
        <div
          v-for="i in 3"
          :key="i"
          class="grid w-full grid-cols-1 items-start gap-3 py-8 lg:grid-cols-2"
        >
          <div class="flex items-center gap-x-2.5 sm:gap-x-4">
            <Skeleton class="aspect-4/5 w-24 shrink-0 rounded-md sm:w-40" />
            <div class="flex flex-col gap-y-2">
              <Skeleton class="h-3.5 w-24" />
              <Skeleton class="h-4 w-48 sm:w-64" />
              <div class="flex flex-col gap-y-1.5">
                <Skeleton class="h-3.5 w-40" />
                <Skeleton class="h-3.5 w-52" />
              </div>
            </div>
          </div>
          <div class="grid grow-0 grid-cols-2 gap-2">
            <Skeleton class="min-h-32 rounded-lg" />
            <div class="flex flex-col items-start gap-y-2 rounded-lg border px-3.5 py-3">
              <Skeleton class="size-8 rounded-lg" />
              <div class="space-y-1">
                <Skeleton class="h-3.5 w-16" />
                <Skeleton class="h-3 w-24" />
              </div>
              <Skeleton class="h-7 w-20" />
            </div>
            <div class="flex flex-col items-start gap-y-2 rounded-lg border px-3.5 py-3">
              <Skeleton class="size-8 rounded-lg" />
              <div class="space-y-1">
                <Skeleton class="h-3.5 w-16" />
                <Skeleton class="h-3 w-24" />
              </div>
              <Skeleton class="h-7 w-12" />
            </div>
            <div class="flex flex-col items-start gap-y-2 rounded-lg border px-3.5 py-3">
              <Skeleton class="size-8 rounded-lg" />
              <div class="space-y-1">
                <Skeleton class="h-3.5 w-14" />
                <Skeleton class="h-3 w-28" />
              </div>
              <Skeleton class="h-7 w-10" />
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Events List -->
    <template v-else>
      <div v-if="events.length" class="divide-border grid grid-cols-1 divide-y border-y">
        <div
          v-for="event in events"
          :key="event.id"
          class="grid w-full grid-cols-1 items-start gap-3 rounded-lg py-8 lg:grid-cols-2"
        >
          <div class="flex flex-col gap-y-2">
            <NuxtLink
              :to="`/projects/${route.params.username}/events/${event.slug}`"
              class="flex items-center gap-x-2.5 sm:gap-x-4"
            >
              <div
                class="bg-muted border-border aspect-4/5 w-24 shrink-0 overflow-hidden rounded-md border sm:w-40"
              >
                <img
                  v-if="event.poster_image?.md"
                  :src="event.poster_image.md"
                  :alt="event.title"
                  class="size-full object-cover select-none"
                  loading="lazy"
                />
              </div>

              <div class="flex flex-col gap-y-1.5">
                <div class="flex flex-wrap items-center gap-2">
                  <span
                    v-if="event.time_status === 'upcoming' && event.start_date"
                    v-tippy="$dayjs(event.start_date).format('MMMM D, YYYY')"
                    class="text-info-foreground shrink-0 text-xs leading-none font-medium tracking-tight sm:text-sm"
                  >
                    Starts {{ $dayjs(event.start_date).fromNow() }}
                  </span>
                  <span
                    v-else
                    class="shrink-0 text-xs leading-none font-medium tracking-tight sm:text-sm"
                    :class="statusConfig[event.time_status]?.class || statusConfig.no_date.class"
                  >
                    {{ statusConfig[event.time_status]?.label || "No date" }}
                  </span>
                  <span
                    v-if="event.status === 'draft'"
                    class="bg-warning/10 text-warning-foreground shrink-0 rounded px-2 py-1 text-xs leading-none font-medium tracking-tight"
                  >
                    Draft
                  </span>
                  <span
                    v-if="event.is_active"
                    class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400"
                  >
                    Active
                  </span>
                </div>

                <p class="line-clamp-2 text-base font-medium tracking-tight">
                  {{ event.title }}
                </p>

                <div class="flex flex-col gap-y-1.5 text-xs tracking-tight sm:text-sm">
                  <span v-if="event.date_label" class="flex items-center gap-x-1.5">
                    <Icon name="hugeicons:calendar-03" class="size-4 shrink-0" />
                    <span class="line-clamp-1">{{ event.date_label }}</span>
                  </span>

                  <span v-if="event.location" class="flex items-center gap-x-1.5">
                    <Icon name="hugeicons:location-01" class="size-4 shrink-0" />
                    <span class="line-clamp-1">{{ event.location }}</span>
                  </span>
                </div>
              </div>
            </NuxtLink>

            <button
              v-if="!event.is_active"
              type="button"
              :disabled="settingActiveId === event.id"
              class="text-muted-foreground hover:text-foreground ml-[6.5rem] flex w-fit items-center gap-x-1 rounded px-1.5 py-0.5 text-xs font-medium tracking-tight transition hover:bg-emerald-100 hover:text-emerald-700 sm:ml-44 dark:hover:bg-emerald-900/30 dark:hover:text-emerald-400"
              @click="handleSetActive(event)"
            >
              <Icon name="hugeicons:tick-02" class="size-3.5" />
              Set as active
            </button>
          </div>

          <div class="grid grow-0 grid-cols-2 gap-2">
            <div
              class="bg-card group relative flex flex-col items-center justify-center gap-y-2 rounded-lg"
            >
              <ChartSemiCircle
                :value="event.booked_area"
                :max="event.gross_area"
                show-max
                :compact="false"
                suffix="m²"
                center-label="area booked"
                :animate-bars="true"
                :animate-value="false"
                class="lg:max-w-[90%]"
              />
            </div>

            <DashboardStatsCard
              title="Revenue"
              description="Confirmed orders"
              :value="event.total_revenue"
              icon="hugeicons:money-bag-02"
              icon-color="text-emerald-500"
              :format="{
                style: 'currency',
                currency: 'IDR',
                notation: 'compact',
                maximumFractionDigits: 0,
              }"
            />
            <DashboardStatsCard
              title="Exhibitors"
              description="Registered brands"
              :value="event.brand_events_count"
              icon="hugeicons:store-02"
              icon-color="text-violet-500"
              cta-label="Add"
              cta-icon="hugeicons:add-01"
              :cta-link="`/projects/${route.params.username}/events/${event.slug}/brands`"
            />
            <DashboardStatsCard
              title="Orders"
              description="Submitted & confirmed"
              :value="event.orders_submitted + event.orders_confirmed"
              icon="hugeicons:shopping-cart-02"
              icon-color="text-amber-500"
            >
              <span
                v-if="event.orders_submitted > 0"
                class="text-xs tracking-tight text-amber-600 dark:text-amber-400"
              >
                {{ event.orders_submitted }} pending
              </span>
            </DashboardStatsCard>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="flex flex-col items-center justify-center py-12">
        <div class="flex flex-col items-center gap-y-3 text-center">
          <div class="bg-muted text-muted-foreground rounded-lg p-3">
            <Icon name="hugeicons:calendar-03" class="size-6" />
          </div>
          <div class="space-y-1">
            <p class="font-medium tracking-tight">No events found</p>
            <p class="text-muted-foreground text-sm tracking-tight">
              {{ search || statusFilter !== "all" ? "Try adjusting your filters." : "Create your first event edition." }}
            </p>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

const props = defineProps({
  project: Object,
});

const route = useRoute();
const client = useSanctumClient();
const { $dayjs } = useNuxtApp();
const settingActiveId = ref(null);

async function handleSetActive(event) {
  settingActiveId.value = event.id;
  try {
    await client(`/api/projects/${route.params.username}/events/${event.slug}/set-active`, {
      method: "POST",
    });
    toast.success(`${event.title} set as active edition`);
    refreshEvents();
  } catch (e) {
    toast.error(e?.data?.message || "Failed to set as active");
  } finally {
    settingActiveId.value = null;
  }
}

const search = ref("");
const statusFilter = ref("all");

const apiUrl = computed(() => {
  const params = new URLSearchParams();
  params.set("per_page", "50");

  if (search.value) {
    params.set("filter[search]", search.value);
  }

  if (statusFilter.value && statusFilter.value !== "all") {
    params.set("filter[status]", statusFilter.value);
  }

  return `/api/projects/${route.params.username}/events?${params.toString()}`;
});

const { data: eventsResponse, pending: loading, refresh: refreshEvents } = await useLazySanctumFetch(apiUrl, {
  key: `events-${route.params.username}`,
  watch: [apiUrl],
});

const events = computed(() => eventsResponse.value?.data || []);

usePageMeta(null, {
  title: computed(() => `Events · ${props.project?.name || ""}`),
});

const statusConfig = {
  ongoing: {
    label: "Ongoing",
    class: "text-destructive-foreground",
  },
  upcoming: {
    label: "Upcoming",
    class: "text-info-foreground",
  },
  completed: {
    label: "Completed",
    class: "text-success-foreground",
  },
  no_date: {
    label: "No date",
    class: "text-muted-foreground",
  },
};
</script>
