<template>
  <div class="space-y-4">
    <h3 class="page-title">Recents Events</h3>

    <!-- Loading -->
    <template v-if="loading">
      <div class="divide-border grid grid-cols-1 divide-y border-y">
        <div
          v-for="i in 3"
          :key="i"
          class="grid w-full grid-cols-1 items-start gap-3 py-8 lg:grid-cols-2"
        >
          <!-- Left: poster + info -->
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

          <!-- Right: 2x2 stats grid -->
          <div class="grid grow-0 grid-cols-2 gap-2">
            <!-- Semi-circle chart placeholder -->
            <Skeleton class="min-h-32 rounded-lg" />
            <!-- Revenue card -->
            <div class="flex flex-col items-start gap-y-2 rounded-lg border px-3.5 py-3">
              <Skeleton class="size-8 rounded-lg" />
              <div class="space-y-1">
                <Skeleton class="h-3.5 w-16" />
                <Skeleton class="h-3 w-24" />
              </div>
              <Skeleton class="h-7 w-20" />
            </div>
            <!-- Exhibitors card -->
            <div class="flex flex-col items-start gap-y-2 rounded-lg border px-3.5 py-3">
              <Skeleton class="size-8 rounded-lg" />
              <div class="space-y-1">
                <Skeleton class="h-3.5 w-16" />
                <Skeleton class="h-3 w-24" />
              </div>
              <Skeleton class="h-7 w-12" />
            </div>
            <!-- Orders card -->
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

    <!-- Empty -->
    <template v-else-if="!events || events.length === 0">
      <div class="flex items-center gap-2 py-4">
        <Icon name="hugeicons:calendar-03" class="text-muted-foreground size-4" />
        <p class="text-muted-foreground text-sm tracking-tight">No events yet</p>
      </div>
    </template>

    <!-- Events List -->
    <div v-else class="divide-border grid grid-cols-1 divide-y border-y">
      <div
        v-for="event in events"
        :key="event.id"
        class="grid w-full grid-cols-1 items-start gap-3 rounded-lg py-8 lg:grid-cols-2"
      >
        <NuxtLink
          :to="`/projects/${event.project_username}/events/${event.slug}`"
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
            <div class="flex items-center gap-2">
              <!-- Time status badge -->
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
              <!-- Draft badge -->
              <span
                v-if="event.status === 'draft'"
                class="bg-warning/10 text-warning-foreground shrink-0 rounded px-2 py-1 text-xs leading-none font-medium tracking-tight"
              >
                Draft
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
            :cta-link="`/projects/${event.project_username}/events/${event.slug}/brands`"
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

        <!-- Stats -->
        <!-- <div class="hidden shrink-0 items-center gap-3 sm:flex">
          <span class="text-muted-foreground text-xs tracking-tight tabular-nums">
            {{ event.brand_events_count }} brands
          </span>
          <span
            v-if="event.orders_submitted > 0"
            class="text-xs tracking-tight text-amber-600 tabular-nums dark:text-amber-400"
          >
            {{ event.orders_submitted }} pending
          </span>
          <span
            v-if="event.orders_confirmed > 0"
            class="text-xs tracking-tight text-green-600 tabular-nums dark:text-green-400"
          >
            {{ event.orders_confirmed }} confirmed
          </span>
        </div> -->
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface EventItem {
  id: number;
  title: string;
  slug: string;
  date_label: string | null;
  start_date: string | null;
  end_date: string | null;
  location: string | null;
  status: string;
  time_status: string;
  project_name: string | null;
  project_username: string | null;
  poster_image: Record<string, string> | null;
  brand_events_count: number;
  orders_submitted: number;
  orders_confirmed: number;
  gross_area: number;
  booked_area: number;
  total_revenue: number;
}

defineProps<{
  events: EventItem[];
  loading?: boolean;
}>();

const { formatPrice } = useFormatters();

const statusConfig: Record<string, { label: string; class: string }> = {
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

function areaPercent(event: EventItem): number {
  if (!event.gross_area) return 0;
  return Math.min(Math.round((event.booked_area / event.gross_area) * 100), 100);
}
</script>
