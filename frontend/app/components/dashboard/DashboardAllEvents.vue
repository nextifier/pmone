<template>
  <div class="space-y-4">
    <h3 class="page-title">Recents Events</h3>

    <!-- Loading -->
    <EventListSkeleton v-if="loading" />

    <!-- Empty -->
    <template v-else-if="!events || events.length === 0">
      <div class="flex items-center gap-2 py-4">
        <Icon name="hugeicons:calendar-03" class="text-muted-foreground size-4" />
        <p class="text-muted-foreground text-sm tracking-tight">No events yet</p>
      </div>
    </template>

    <!-- Events List -->
    <div v-else class="divide-border grid grid-cols-1 divide-y border-y">
      <EventListItem
        v-for="event in events"
        :key="event.id"
        :event="event"
      />
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

defineProps<{
  events: EventItem[];
  loading?: boolean;
}>();
</script>
