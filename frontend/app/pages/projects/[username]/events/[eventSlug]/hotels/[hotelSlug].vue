<template>
  <div class="flex flex-col gap-y-0">
    <div v-if="pending" class="flex justify-center py-10">
      <Spinner class="size-6" />
    </div>

    <div
      v-else-if="!hotel"
      class="text-muted-foreground rounded-md border border-dashed py-12 text-center text-sm tracking-tight"
    >
      Hotel not found.
    </div>

    <template v-else>
      <div class="mb-6 grid gap-5 lg:grid-cols-[12rem_1fr_12rem] lg:items-start">
        <div class="bg-muted aspect-4/5 w-full overflow-hidden rounded-xl lg:w-48">
          <img
            v-if="hotel.featured?.lg || hotel.featured?.url"
            :src="hotel.featured?.lg || hotel.featured?.url"
            :alt="hotel.name"
            class="size-full object-cover select-none"
          />
        </div>

        <div class="flex min-w-0 flex-col items-start gap-y-2 lg:pt-3">
          <p
            v-if="event?.title"
            class="text-muted-foreground inline-flex gap-1.5 text-xs tracking-tight sm:text-sm"
          >
            <span>{{ event.title }}</span>
            <span>·</span>
            <span>Hotel</span>
          </p>

          <h1 class="text-xl font-semibold tracking-tighter sm:text-2xl">{{ hotel.name }}</h1>

          <div class="flex flex-wrap items-center gap-1.5">
            <span
              v-if="hotel.star_rating"
              class="text-primary border-primary/30 bg-primary/5 inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium tracking-tight"
            >
              <Icon name="material-symbols:star-rounded" class="size-3.5" />
              {{ hotel.star_rating }}-star
            </span>
            <span
              v-if="hotel.is_active"
              class="border-border text-muted-foreground shrink-0 rounded-full border px-2.5 py-1 text-xs font-medium tracking-tight"
            >
              Active
            </span>
            <span
              v-else
              class="border-border text-muted-foreground shrink-0 rounded-full border px-2.5 py-1 text-xs font-medium tracking-tight"
            >
              Inactive
            </span>
          </div>

          <div class="mt-2 flex flex-col gap-x-6 gap-y-4 sm:flex-row sm:flex-wrap">
            <div
              v-for="meta in metaItems"
              :key="meta.label"
              class="flex items-center gap-x-2"
            >
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
                  class="text-sm font-medium tracking-tight hover:underline"
                >
                  {{ meta.value }}
                </a>
                <p v-else class="text-sm font-medium tracking-tight">{{ meta.value }}</p>
                <p v-if="meta.subtitle" class="text-muted-foreground text-xs sm:text-sm tracking-tight">
                  {{ meta.subtitle }}
                </p>
              </div>
            </div>
          </div>

          <p
            v-if="hotel.description"
            class="text-sm tracking-tight whitespace-pre-line mt-3"
          >
            {{ hotel.description }}
          </p>

          <div v-if="canEdit" class="mt-3 flex flex-wrap items-center gap-2">
            <Button :to="`${base}/edit`" size="sm">
              <Icon name="hugeicons:edit-02" class="size-4" />
              Edit Hotel
            </Button>
          </div>
        </div>

        <div class="bg-muted aspect-4/5 w-full overflow-hidden rounded-xl lg:w-48">
          <iframe
            v-if="mapEmbedUrl"
            :src="mapEmbedUrl"
            class="size-full"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            allowfullscreen
          />
        </div>
      </div>

      <TabNav :tabs="hotelTabs" />

      <div ref="hotelArea" class="pt-6">
        <NuxtPage :event="event" :project="project" :hotel="hotel" @refresh="refresh" />
      </div>
    </template>
  </div>
</template>

<script setup>
import { TabNav } from "@/components/ui/tab-nav";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["hotels.read"],
  layout: "app",
});

const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();
const hotelSlug = computed(() => route.params.hotelSlug);
const hotelArea = ref(null);

const base = computed(
  () =>
    `/projects/${route.params.username}/events/${route.params.eventSlug}/hotels/${hotelSlug.value}`,
);

const { hasPermission } = usePermission();
const canEdit = computed(() => hasPermission("hotels.update"));

const { data, pending, refresh } = await useLazySanctumFetch(
  () => `/api/events/${props.event?.id}/hotels/${hotelSlug.value}`,
  { key: () => `hotel-detail-${props.event?.id}-${hotelSlug.value}` },
);

const hotel = computed(() => data.value?.data);

usePageMeta(null, {
  title: computed(() => `${hotel.value?.name ?? "Hotel"} · Hotels`),
});

const hotelTabs = computed(() => [
  { label: "Room Types", icon: "hugeicons:bed-single-01", to: base.value, exact: true },
  { label: "Allotments", icon: "hugeicons:calendar-03", to: `${base.value}/allotments` },
  { label: "Transfers", icon: "hugeicons:car-01", to: `${base.value}/transfers` },
]);

const eventTabs = inject("eventTabs", null);
useTabSwipe(hotelArea, hotelTabs, { parentTabs: eventTabs });

const metaItems = computed(() => {
  if (!hotel.value) return [];
  const parts = [hotel.value.address, hotel.value.city, hotel.value.country].filter(Boolean);
  return [
    {
      label: "Location",
      icon: "hugeicons:location-01",
      value: parts.join(", ") || "-",
      href: hotel.value.google_maps_link || undefined,
    },
    {
      label: "Contact",
      icon: "hugeicons:mail-01",
      value: hotel.value.contact_email || "-",
      subtitle: hotel.value.contact_phone || undefined,
    },
    {
      label: "Check-in / Check-out",
      icon: "hugeicons:clock-01",
      value: "14:00 / 12:00",
    },
    {
      label: "Commission / Tax / Service",
      icon: "hugeicons:percent-square",
      value: `${Number(hotel.value.commission_rate).toFixed(2)}% / ${Number(hotel.value.tax_percentage).toFixed(2)}% / ${Number(hotel.value.service_charge_percentage).toFixed(2)}%`,
    },
  ];
});

const mapEmbedUrl = computed(() => {
  if (!hotel.value) return null;
  const queryParts = [
    hotel.value.name,
    hotel.value.address,
    hotel.value.city,
    hotel.value.country,
  ]
    .filter(Boolean)
    .join(", ");

  if (queryParts) {
    return `https://maps.google.com/maps?q=${encodeURIComponent(queryParts)}&output=embed`;
  }

  const src = hotel.value.google_maps_embed_src?.trim();
  if (src && /^https:\/\/www\.google\.com\/maps\/embed\?pb=/.test(src)) {
    return src;
  }

  return null;
});
</script>
