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
      <div class="mb-4">
        <ButtonBack
          :destination="`/projects/${route.params.username}/events/${route.params.eventSlug}/hotels`"
          force-destination
        />
      </div>

      <div class="mb-6 grid gap-5 lg:grid-cols-[12rem_1fr_12rem] lg:items-start">
        <div class="bg-muted aspect-4/5 w-full overflow-hidden rounded-xl lg:w-48">
          <Lightbox
            v-if="galleryItems.length"
            :items="galleryItems"
            :alt="hotel.name"
            thumbnail-key="md"
          >
            <template #trigger="{ open }">
              <button type="button" class="block size-full cursor-zoom-in" @click="open">
                <img
                  :src="galleryItems[0].md || galleryItems[0].lg || galleryItems[0].url"
                  :alt="hotel.name"
                  class="size-full object-cover select-none"
                />
              </button>
            </template>
          </Lightbox>
          <div v-else class="from-muted to-muted/40 size-full bg-gradient-to-br" />
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
            <Button as-child size="sm">
              <NuxtLink :to="`${base}/edit`">
                <Icon name="hugeicons:edit-02" class="size-4" />
                Edit Hotel
              </NuxtLink>
            </Button>
            <Button variant="outline" size="sm" @click="pivotDialogOpen = true">
              <Icon name="hugeicons:settings-02" class="size-4" />
              Event settings
            </Button>
            <NuxtLink
              :to="`/hotels-master/${hotelSlug}`"
              target="_blank"
              class="border-border hover:bg-muted inline-flex items-center gap-x-1 rounded-md border px-3 py-1.5 text-sm tracking-tight active:scale-98"
            >
              <Icon name="hugeicons:link-square-02" class="size-4" />
              Open in master
            </NuxtLink>
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

      <div class="pt-6">
        <NuxtPage :event="event" :project="project" :hotel="hotel" @refresh="refresh" />
      </div>

      <DialogResponsive v-model:open="pivotDialogOpen" dialog-max-width="28rem">
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="space-y-1">
            <h3 class="page-title">Event settings</h3>
            <p class="page-description">
              Controls how this hotel behaves for "{{ event?.title || "this event" }}". Master hotel
              fields (name, address, gallery) are global - edit them in the master page.
            </p>
          </div>

          <form @submit.prevent="savePivot" class="mt-4 space-y-4">
            <label class="flex items-start gap-x-2">
              <Checkbox id="pivot-active" v-model="pivotForm.is_active" class="mt-0.5" />
              <div class="space-y-0.5">
                <Label for="pivot-active" class="cursor-pointer">Active for this event</Label>
                <p class="text-muted-foreground text-xs tracking-tight">
                  When off, public booking flow hides this hotel from the event listing.
                </p>
              </div>
            </label>

            <div class="space-y-2">
              <Label>Notes</Label>
              <Textarea
                v-model="pivotForm.notes"
                placeholder="Internal notes about this hotel for this event"
                rows="3"
              />
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <Button variant="outline" type="button" @click="pivotDialogOpen = false">Cancel</Button>
              <Button type="submit" :disabled="pivotSaving">
                <Spinner v-if="pivotSaving" />
                Save settings
              </Button>
            </div>
          </form>
        </div>
      </DialogResponsive>
    </template>
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { TabNav } from "@/components/ui/tabs";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Lightbox } from "@/components/ui/lightbox";
import { Spinner } from "@/components/ui/spinner";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "vue-sonner";

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

const client = useSanctumClient();
const pivotDialogOpen = ref(false);
const pivotSaving = ref(false);
const pivotForm = reactive({ is_active: true, notes: "" });

watch(
  hotel,
  (h) => {
    if (!h) return;
    const pivotEv = h.events?.[0]?.pivot ?? null;
    pivotForm.is_active = pivotEv?.is_active !== false;
    pivotForm.notes = pivotEv?.notes ?? "";
  },
  { immediate: true }
);

async function savePivot() {
  pivotSaving.value = true;
  try {
    await client(`/api/events/${props.event.id}/hotels/${hotelSlug.value}`, {
      method: "PUT",
      body: {
        pivot: {
          is_active: pivotForm.is_active,
          notes: pivotForm.notes || null,
        },
      },
    });
    toast.success("Event settings saved");
    pivotDialogOpen.value = false;
    await refresh();
  } catch (err) {
    toast.error("Save failed", { description: err?.data?.message || err?.message });
  } finally {
    pivotSaving.value = false;
  }
}

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

const galleryItems = computed(() => {
  if (!hotel.value) return [];
  const items = [];
  if (hotel.value.featured) {
    items.push({
      sm: hotel.value.featured.sm,
      md: hotel.value.featured.md,
      lg: hotel.value.featured.lg || hotel.value.featured.url,
      url: hotel.value.featured.url,
      alt: hotel.value.name,
    });
  }
  for (const media of hotel.value.media ?? []) {
    if (hotel.value.featured && media.id === hotel.value.featured.id) continue;
    items.push({
      sm: media.sm,
      md: media.md,
      lg: media.lg || media.url,
      url: media.url,
      alt: hotel.value.name,
    });
  }
  return items;
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
