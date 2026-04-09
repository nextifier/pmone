<template>
  <div v-if="event" class="flex flex-col gap-y-6 lg:gap-y-10">
    <!-- Hero: Poster + Event Info -->
    <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
      <!-- Poster Image -->
      <div class="bg-muted aspect-4/5 w-full shrink-0 overflow-hidden rounded-xl sm:w-40 lg:w-48">
        <img
          v-if="event.poster_image?.lg || event.poster_image?.url"
          :src="event.poster_image?.lg || event.poster_image?.url"
          :alt="event.title"
          class="size-full object-cover select-none"
        />
      </div>

      <!-- Event Info -->
      <div class="flex flex-col items-start gap-y-2 sm:pt-3">
        <!-- Project Context -->
        <p
          v-if="project?.name || event.edition_number_with_ordinal"
          class="text-muted-foreground inline-flex gap-1.5 text-xs tracking-tight sm:text-sm"
        >
          <span v-if="project?.name">{{ project.name }}</span>
          <span v-if="project?.name && event.edition_number_with_ordinal">·</span>
          <span v-if="event.edition_number_with_ordinal"
            >{{ event.edition_number_with_ordinal }} Edition</span
          >
        </p>

        <!-- Event Title -->
        <h1 class="text-xl font-semibold tracking-tighter sm:text-2xl">{{ event.title }}</h1>

        <!-- Badges Row -->
        <div class="flex flex-wrap items-center gap-1.5">
          <span
            class="border-border text-muted-foreground shrink-0 rounded-full border px-2.5 py-1 text-xs font-medium tracking-tight capitalize"
          >
            {{ event.status }}
          </span>
          <span
            class="border-border text-muted-foreground shrink-0 rounded-full border px-2.5 py-1 text-xs font-medium tracking-tight capitalize"
          >
            {{ event.visibility }}
          </span>
          <span
            v-if="event.is_active"
            class="border-border text-muted-foreground shrink-0 rounded-full border px-2.5 py-1 text-xs font-medium tracking-tight capitalize"
          >
            Active Edition
          </span>
        </div>

        <!-- Metadata Grid -->
        <div class="mt-2 flex flex-col gap-x-6 gap-y-4 sm:flex-row sm:flex-wrap">
          <div v-for="meta in metaItems" :key="meta.label" class="flex items-center gap-x-2">
            <div
              class="bg-muted text-muted-foreground flex size-8 shrink-0 items-center justify-center rounded-lg"
            >
              <Icon :name="meta.icon" class="size-4" />
            </div>
            <div class="min-w-0">
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                {{ meta.label }}
              </p>
              <a
                v-if="meta.href"
                :href="meta.href"
                target="_blank"
                rel="noopener noreferrer"
                class="text-sm font-medium tracking-tight underline underline-offset-2"
              >
                {{ meta.value }}
              </a>
              <p v-else class="text-sm font-medium tracking-tight">{{ meta.value }}</p>
              <p v-if="meta.subtitle" class="text-muted-foreground text-xs sm:text-sm">
                {{ meta.subtitle }}
              </p>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div v-if="event.can_edit" class="mt-3 flex flex-wrap items-center gap-2">
          <NuxtLink
            :to="`${base}/details`"
            class="hover:bg-primary/80 bg-primary text-primary-foreground flex items-center gap-x-1.5 rounded-lg px-3 py-1.5 text-sm font-medium tracking-tight transition active:scale-98"
          >
            <Icon name="hugeicons:edit-02" class="size-4" />
            <span>Edit Details</span>
          </NuxtLink>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <EventStatsGrid
      :event="event"
      :brands-link="`${base}/brands`"
      :orders-link="`${base}/operational/orders`"
      :nav-cards="[
        {
          label: 'Operational',
          icon: 'hugeicons:briefcase-01',
          description: 'Orders, products, and order form settings',
          to: `${base}/operational/orders`,
        },
        {
          label: 'Content',
          icon: 'hugeicons:note-01',
          description: 'Rundown, programs, FAQ, and more',
          to: `${base}/content/rundown`,
        },
      ]"
      chart-class="max-w-[100%]"
      grid-class="grid-cols-2 sm:grid-cols-[repeat(auto-fit,minmax(200px,1fr))]"
    />
  </div>
</template>

<script setup>
const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();

const base = computed(() => `/projects/${route.params.username}/events/${route.params.eventSlug}`);

const metaItems = computed(() =>
  [
    { label: "Date", icon: "hugeicons:calendar-03", value: props.event?.date_label },
    {
      label: "Time",
      icon: "hugeicons:clock-01",
      value: props.event?.start_time
        ? `${props.event.start_time}${props.event.end_time ? ` - ${props.event.end_time}` : ""}`
        : null,
    },
    {
      label: "Location",
      icon: "hugeicons:location-01",
      value: props.event?.location,
      href: props.event?.location_link || undefined,
      subtitle: props.event?.hall || undefined,
    },
  ].filter((m) => m.value)
);
</script>
