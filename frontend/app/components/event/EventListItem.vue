<template>
  <div
    class="grid w-full grid-cols-1 items-start gap-3 rounded-lg py-8 lg:grid-cols-2"
  >
    <div class="flex flex-col gap-y-2">
      <NuxtLink
        :to="eventLink"
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
          <div class="flex items-center gap-2" :class="{ 'flex-wrap': wrapBadges }">
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
            <slot name="badges" :event="event" />
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

      <slot name="actions" :event="event" />
    </div>

    <div class="grid grow-0 grid-cols-2 gap-2">
      <div
        class="group relative flex flex-col items-center justify-center gap-y-2"
      >
        <ChartSemiCircle
          :value="event.booked_area"
          :max="event.saleable_area"
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
  saleable_area: number;
  booked_area: number;
  total_revenue: number;
}

const props = defineProps<{
  event: EventItem;
  wrapBadges?: boolean;
}>();

const eventLink = computed(
  () => `/projects/${props.event.project_username}/events/${props.event.slug}`
);

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
</script>
