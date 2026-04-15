<template>
  <div
    v-if="skeleton"
    class="grid w-full grid-cols-1 items-start gap-4 rounded-lg lg:grid-cols-2"
  >
    <div class="flex items-center gap-x-2.5 sm:gap-x-4">
      <Skeleton class="aspect-4/5 w-26 shrink-0 rounded-md sm:w-40" />
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
      <div
        v-for="i in 3"
        :key="i"
        class="flex flex-col items-start gap-y-2 rounded-lg border px-3.5 py-3"
      >
        <Skeleton class="size-8 rounded-lg" />
        <div class="space-y-1">
          <Skeleton class="h-3.5 w-16" />
          <Skeleton class="h-3 w-24" />
        </div>
        <Skeleton class="h-7 w-20" />
      </div>
      <Skeleton class="min-h-32 rounded-lg" />
    </div>
  </div>

  <div
    v-else-if="event"
    class="grid w-full grid-cols-1 items-start gap-4 rounded-lg lg:grid-cols-2"
  >
    <div class="flex items-start gap-x-2.5 sm:items-center sm:gap-x-4">
      <NuxtLink :to="eventLink" class="shrink-0">
        <div
          class="bg-muted border-border aspect-4/5 w-26 overflow-hidden rounded-md border sm:w-40"
        >
          <img
            v-if="event.poster_image"
            :src="
              event.poster_image?.md ||
              event.poster_image?.sm ||
              event.poster_image?.lg ||
              event.poster_image?.url
            "
            :alt="event.title"
            class="size-full object-cover select-none"
            loading="lazy"
          />
        </div>
      </NuxtLink>

      <div class="flex flex-col items-start gap-y-1.5 pt-2 sm:pt-0">
        <div class="flex items-center gap-2" :class="{ 'flex-wrap': wrapBadges }">
          <!-- Time status badge -->
          <span
            v-if="event.time_status === 'upcoming' && event.start_date"
            v-tippy="$dayjs(event.start_date).format('MMMM D, YYYY')"
            class="text-foreground shrink-0 text-xs leading-none font-medium tracking-tight sm:text-sm"
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

        <NuxtLink :to="eventLink">
          <h3
            class="text-foreground text-lg leading-tight! font-medium tracking-tighter text-balance sm:text-xl"
          >
            {{ event.title }}
          </h3>
        </NuxtLink>

        <div class="flex flex-col items-start gap-y-1.5 text-sm tracking-tight">
          <span v-if="event.date_label" class="flex items-center gap-x-1.5">
            <Icon name="hugeicons:calendar-03" class="size-4 shrink-0" />
            <span class="line-clamp-1">{{ event.date_label }}</span>
          </span>

          <span v-if="event.location" class="flex items-center gap-x-1.5">
            <Icon name="hugeicons:location-01" class="size-4 shrink-0" />
            <span class="line-clamp-1">{{ event.location }}</span>
          </span>

          <slot name="status" :event="event" />
        </div>
      </div>
    </div>

    <EventStatsGrid
      :event="event"
      :brands-link="`/projects/${event.project_username}/events/${event.slug}/brands`"
      :orders-link="`/projects/${event.project_username}/events/${event.slug}/operational/orders`"
      chart-class="max-w-[240px]"
    />
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
  event?: EventItem;
  wrapBadges?: boolean;
  skeleton?: boolean;
}>();

const eventLink = computed(() =>
  props.event ? `/projects/${props.event.project_username}/events/${props.event.slug}` : "#"
);

const statusConfig: Record<string, { label: string; class: string }> = {
  ongoing: {
    label: "Ongoing",
    class: "text-destructive-foreground",
  },
  upcoming: {
    label: "Upcoming",
    class: "text-foreground",
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
