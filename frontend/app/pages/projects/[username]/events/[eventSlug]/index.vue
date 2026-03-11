<template>
  <div v-if="event" class="flex flex-col gap-y-6">
    <!-- Hero: Poster + Event Info -->
    <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
      <!-- Poster Image - Always visible -->
      <div
        class="bg-muted border-border aspect-4/5 w-full shrink-0 overflow-hidden rounded-xl border sm:w-40 lg:w-48"
      >
        <img
          v-if="event.poster_image?.lg || event.poster_image?.url"
          :src="event.poster_image?.lg || event.poster_image?.url"
          :alt="event.title"
          class="size-full object-cover select-none"
        />
      </div>

      <!-- Event Info -->
      <div class="flex min-w-0 flex-1 flex-col gap-y-4">
        <!-- Badges Row -->
        <div class="flex flex-wrap items-center gap-1.5">
          <span
            class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium tracking-tight capitalize"
            :class="statusBadgeClass"
          >
            {{ event.status }}
          </span>
          <span
            class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium tracking-tight capitalize"
            :class="visibilityBadgeClass"
          >
            {{ event.visibility }}
          </span>
          <span
            v-if="event.is_active"
            class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium tracking-tight text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400"
          >
            Active Edition
          </span>
        </div>

        <!-- Tagline -->
        <p v-if="event.tagline" class="text-muted-foreground text-sm tracking-tight italic">
          "{{ event.tagline }}"
        </p>

        <!-- Metadata Grid -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div v-if="event.date_label" class="flex items-center gap-x-2.5">
            <div class="bg-muted text-muted-foreground flex size-8 shrink-0 items-center justify-center rounded-lg">
              <Icon name="hugeicons:calendar-03" class="size-4" />
            </div>
            <div class="min-w-0">
              <p class="text-muted-foreground text-xs sm:text-sm">Date</p>
              <p class="text-sm font-medium tracking-tight">{{ event.date_label }}</p>
            </div>
          </div>

          <div v-if="event.start_time" class="flex items-center gap-x-2.5">
            <div class="bg-muted text-muted-foreground flex size-8 shrink-0 items-center justify-center rounded-lg">
              <Icon name="hugeicons:clock-01" class="size-4" />
            </div>
            <div class="min-w-0">
              <p class="text-muted-foreground text-xs sm:text-sm">Time</p>
              <p class="text-sm font-medium tracking-tight">
                {{ event.start_time }}{{ event.end_time ? ` - ${event.end_time}` : "" }}
              </p>
            </div>
          </div>

          <div v-if="event.location" class="flex items-center gap-x-2.5">
            <div class="bg-muted text-muted-foreground flex size-8 shrink-0 items-center justify-center rounded-lg">
              <Icon name="hugeicons:location-01" class="size-4" />
            </div>
            <div class="min-w-0">
              <p class="text-muted-foreground text-xs sm:text-sm">Location</p>
              <p class="text-sm font-medium tracking-tight">{{ event.location }}</p>
              <p v-if="event.hall" class="text-muted-foreground text-xs sm:text-sm">{{ event.hall }}</p>
            </div>
          </div>

          <div v-if="event.edition_label" class="flex items-center gap-x-2.5">
            <div class="bg-muted text-muted-foreground flex size-8 shrink-0 items-center justify-center rounded-lg">
              <Icon name="hugeicons:layers-01" class="size-4" />
            </div>
            <div class="min-w-0">
              <p class="text-muted-foreground text-xs sm:text-sm">Edition</p>
              <p class="text-sm font-medium tracking-tight">{{ event.edition_label }}</p>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center gap-2 pt-1">
          <NuxtLink
            :to="`${base}/details`"
            class="border-border hover:bg-muted flex items-center gap-x-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium tracking-tight transition active:scale-98"
          >
            <Icon name="hugeicons:edit-02" class="size-4" />
            <span>Edit Details</span>
          </NuxtLink>
          <NuxtLink
            :to="`${base}/settings`"
            class="border-border hover:bg-muted flex items-center gap-x-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium tracking-tight transition active:scale-98"
          >
            <Icon name="hugeicons:settings-02" class="size-4" />
            <span>Settings</span>
          </NuxtLink>
        </div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 gap-2 lg:grid-cols-4">
      <DashboardStatsCard
        title="Exhibitors"
        description="Registered brands"
        :value="event.brand_events_count ?? 0"
        icon="hugeicons:store-02"
        icon-color="text-violet-500"
        cta-label="Add"
        cta-icon="hugeicons:add-01"
        :cta-link="`${base}/brands`"
      />
      <DashboardStatsCard
        title="Revenue"
        description="Confirmed orders"
        :value="event.total_revenue ?? 0"
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
        title="Orders"
        description="Submitted & confirmed"
        :value="(event.orders_submitted ?? 0) + (event.orders_confirmed ?? 0)"
        icon="hugeicons:shopping-cart-02"
        icon-color="text-amber-500"
        :href="`${base}/operational/orders`"
      >
        <span
          v-if="event.orders_submitted > 0"
          class="text-xs tracking-tight text-amber-600 dark:text-amber-400"
        >
          {{ event.orders_submitted }} pending
        </span>
      </DashboardStatsCard>
      <DashboardStatsCard
        title="Area Booked"
        :description="event.saleable_area ? `of ${event.saleable_area} m² saleable` : 'Total booked area'"
        :value="event.booked_area ?? 0"
        icon="hugeicons:square-arrow-expand-02"
        icon-color="text-sky-500"
        :format="{ style: 'decimal', maximumFractionDigits: 0 }"
      />
    </div>

    <!-- Area Booked Chart -->
    <div v-if="event.saleable_area" class="flex justify-center">
      <ChartSemiCircle
        :value="event.booked_area ?? 0"
        :max="event.saleable_area"
        show-max
        :compact="false"
        suffix="m²"
        center-label="area booked"
        :animate-bars="true"
        :animate-value="false"
        class="max-w-xs"
      />
    </div>

    <!-- Quick Navigation -->
    <div class="space-y-3">
      <h4 class="text-muted-foreground text-sm font-semibold tracking-tighter">Manage</h4>
      <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
        <NuxtLink
          v-for="card in navCards"
          :key="card.to"
          :to="card.to"
          class="border-border hover:bg-muted/50 group flex items-center gap-x-3 rounded-xl border p-4 transition active:scale-[0.99]"
        >
          <div
            class="flex size-9 shrink-0 items-center justify-center rounded-lg"
            :class="card.iconBg"
          >
            <Icon :name="card.icon" class="size-4.5" :class="card.iconColor" />
          </div>
          <div class="min-w-0">
            <h4 class="text-sm font-medium tracking-tight">{{ card.label }}</h4>
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">{{ card.description }}</p>
          </div>
          <Icon
            name="hugeicons:arrow-right-02"
            class="text-muted-foreground ml-auto size-4 shrink-0 opacity-0 transition group-hover:opacity-100"
          />
        </NuxtLink>
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

const statusBadgeClass = computed(() => {
  const map = {
    draft: "bg-warning/10 text-warning-foreground",
    published: "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400",
    archived: "bg-muted text-muted-foreground",
    cancelled: "bg-destructive/10 text-destructive-foreground",
  };
  return map[props.event?.status] || "bg-muted text-muted-foreground";
});

const visibilityBadgeClass = computed(() => {
  return props.event?.visibility === "public"
    ? "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400"
    : "bg-muted text-muted-foreground";
});

const navCards = computed(() => [
  {
    label: "Brands",
    icon: "hugeicons:store-02",
    description: "Manage brands and exhibitors",
    iconBg: "bg-violet-500/10",
    iconColor: "text-violet-500",
    to: `${base.value}/brands`,
  },
  {
    label: "Operational",
    icon: "hugeicons:briefcase-01",
    description: "Orders, products, and order form settings",
    iconBg: "bg-amber-500/10",
    iconColor: "text-amber-500",
    to: `${base.value}/operational/orders`,
  },
  {
    label: "Content",
    icon: "hugeicons:note-01",
    description: "Rundown, programs, FAQ, and more",
    iconBg: "bg-blue-500/10",
    iconColor: "text-blue-500",
    to: `${base.value}/content/rundown`,
  },
  {
    label: "Product Categories",
    icon: "hugeicons:layers-01",
    description: "Organize product categories",
    iconBg: "bg-emerald-500/10",
    iconColor: "text-emerald-500",
    to: `${base.value}/product-categories`,
  },
]);
</script>
