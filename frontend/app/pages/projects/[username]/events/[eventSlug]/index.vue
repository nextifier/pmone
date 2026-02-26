<template>
  <div class="flex flex-col gap-y-6">
    <div class="space-y-1">
      <h3 class="text-lg font-semibold tracking-tight">Overview</h3>
      <p class="text-muted-foreground text-sm tracking-tight">Event summary and quick access.</p>
    </div>

    <div v-if="event" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <div class="space-y-6 lg:col-span-2">
        <!-- Event Info Card -->
        <div class="border-border rounded-xl border p-5">
          <div class="space-y-4">
            <div v-if="event.tagline" class="text-muted-foreground text-sm tracking-tight italic">
              "{{ event.tagline }}"
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div v-if="event.date_label" class="flex items-center gap-x-2.5">
                <div class="bg-muted text-muted-foreground rounded-lg p-2">
                  <Icon name="hugeicons:calendar-03" class="size-4" />
                </div>
                <div>
                  <p class="text-muted-foreground text-xs">Date</p>
                  <p class="text-sm font-medium tracking-tight">{{ event.date_label }}</p>
                </div>
              </div>

              <div v-if="event.start_time" class="flex items-center gap-x-2.5">
                <div class="bg-muted text-muted-foreground rounded-lg p-2">
                  <Icon name="hugeicons:clock-01" class="size-4" />
                </div>
                <div>
                  <p class="text-muted-foreground text-xs">Time</p>
                  <p class="text-sm font-medium tracking-tight">
                    {{ event.start_time }}{{ event.end_time ? ` - ${event.end_time}` : "" }}
                  </p>
                </div>
              </div>

              <div v-if="event.location" class="flex items-center gap-x-2.5">
                <div class="bg-muted text-muted-foreground rounded-lg p-2">
                  <Icon name="hugeicons:location-01" class="size-4" />
                </div>
                <div>
                  <p class="text-muted-foreground text-xs">Location</p>
                  <p class="text-sm font-medium tracking-tight">{{ event.location }}</p>
                  <p v-if="event.hall" class="text-muted-foreground text-xs">{{ event.hall }}</p>
                </div>
              </div>

              <div v-if="event.edition_label" class="flex items-center gap-x-2.5">
                <div class="bg-muted text-muted-foreground rounded-lg p-2">
                  <Icon name="hugeicons:layers-01" class="size-4" />
                </div>
                <div>
                  <p class="text-muted-foreground text-xs">Edition</p>
                  <p class="text-sm font-medium tracking-tight">{{ event.edition_label }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Navigation Cards -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <NuxtLink
            v-for="card in navCards"
            :key="card.to"
            :to="card.to"
            class="border-border hover:bg-muted/50 group flex items-center gap-x-3 rounded-xl border p-4 transition active:scale-[0.99]"
          >
            <div class="bg-muted text-muted-foreground rounded-lg p-2">
              <Icon :name="card.icon" class="size-5" />
            </div>
            <div>
              <h4 class="font-medium tracking-tight">{{ card.label }}</h4>
              <p class="text-muted-foreground text-xs tracking-tight">{{ card.description }}</p>
            </div>
          </NuxtLink>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="space-y-4">
        <div v-if="event.poster_image" class="overflow-hidden rounded-xl">
          <img
            :src="event.poster_image?.md || event.poster_image?.url"
            :alt="event.title"
            class="w-full object-cover"
          />
        </div>

        <div class="border-border rounded-xl border p-4">
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-muted-foreground text-xs">Status</span>
              <span class="text-xs font-medium capitalize">{{ event.status }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-muted-foreground text-xs">Visibility</span>
              <span class="text-xs font-medium capitalize">{{ event.visibility }}</span>
            </div>
            <div v-if="event.created_at" class="flex items-center justify-between">
              <span class="text-muted-foreground text-xs">Created</span>
              <span class="text-xs font-medium">{{
                new Date(event.created_at).toLocaleDateString()
              }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();

const base = computed(() => `/projects/${route.params.username}/events/${route.params.eventSlug}`);

const navCards = computed(() => [
  {
    label: "Brands",
    icon: "hugeicons:blockchain-01",
    description: "Manage brands and exhibitors",
    to: `${base.value}/brands`,
  },
  {
    label: "Rundown",
    icon: "hugeicons:time-schedule",
    description: "Event schedule and activities",
    to: `${base.value}/rundown`,
  },
  {
    label: "Tickets",
    icon: "hugeicons:ticket-01",
    description: "Ticket types and availability",
    to: `${base.value}/tickets`,
  },
  {
    label: "Programs",
    icon: "hugeicons:presentation-bar-chart-01",
    description: "Event programs and sessions",
    to: `${base.value}/programs`,
  },
  {
    label: "FAQ",
    icon: "hugeicons:help-circle",
    description: "Frequently asked questions",
    to: `${base.value}/faq`,
  },
  {
    label: "Partners",
    icon: "hugeicons:agreement-01",
    description: "Event partners and sponsors",
    to: `${base.value}/partners`,
  },
  {
    label: "Gallery",
    icon: "hugeicons:image-02",
    description: "Event photos and media",
    to: `${base.value}/gallery`,
  },
  {
    label: "Settings",
    icon: "hugeicons:settings-01",
    description: "Event configuration",
    to: `${base.value}/settings`,
  },
]);
</script>
