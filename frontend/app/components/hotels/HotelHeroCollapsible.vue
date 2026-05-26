<script setup>
import { Skeleton } from "../ui/skeleton";
import { Badge } from "../ui/badge";
import { Lightbox } from "../ui/lightbox";
import { computed, ref, watch } from "vue";

const props = defineProps({
  hotel: { type: Object, default: null },
  collapsed: { type: Boolean, default: false },
});

// When `collapsed` (driven by booking step), the hero swaps to a compact
// summary card pattern (Booking.com / Airbnb style): small thumbnail + name
// + key context + a "View details" toggle that lets the user expand the full
// hero in-place without leaving their step.
const userExpanded = ref(false);

// Reset the user's expand override whenever the booking step transitions
// (i.e. `collapsed` prop flips). Stops a stale "expanded" state leaking
// across navigations.
watch(
  () => props.collapsed,
  () => {
    userExpanded.value = false;
  }
);

const isCompact = computed(() => props.collapsed && !userExpanded.value);

const eventDateRange = computed(() => {
  const ev = props.hotel?.event;
  if (!ev?.start_date || !ev?.end_date) return "";
  const fmt = new Intl.DateTimeFormat("en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
  return `${fmt.format(new Date(ev.start_date))} - ${fmt.format(new Date(ev.end_date))}`;
});

const galleryItems = computed(() => {
  const hotel = props.hotel;
  if (!hotel) return [];
  const items = [];
  if (hotel.featured) {
    items.push({
      sm: hotel.featured.sm,
      md: hotel.featured.md,
      lg: hotel.featured.lg || hotel.featured.url,
      url: hotel.featured.url,
      alt: hotel.name,
    });
  }
  for (const media of hotel.gallery ?? []) {
    if (hotel.featured && media.id === hotel.featured.id) continue;
    items.push({
      sm: media.sm,
      md: media.md,
      lg: media.lg || media.url,
      url: media.url,
      alt: hotel.name,
    });
  }
  return items;
});

const mapEmbedUrl = computed(() => {
  const hotel = props.hotel;
  if (!hotel) return null;
  const src = hotel.google_maps_embed_src?.trim();
  if (src && /^https:\/\/www\.google\.com\/maps\/embed\?pb=/.test(src)) {
    return src;
  }
  const queryParts = [hotel.name, hotel.address, hotel.city, hotel.country]
    .filter(Boolean)
    .join(", ");
  if (queryParts) {
    return `https://maps.google.com/maps?q=${encodeURIComponent(queryParts)}&output=embed`;
  }
  return null;
});

const fullAddress = computed(() => {
  const hotel = props.hotel;
  if (!hotel) return "";
  return [hotel.address, hotel.city, hotel.country].filter(Boolean).join(", ");
});

const mapsLinkUrl = computed(() => {
  const hotel = props.hotel;
  if (!hotel) return null;
  const query = [hotel.name, hotel.address, hotel.city, hotel.country]
    .filter(Boolean)
    .join(", ");
  if (!query) return null;
  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
});

const compactThumb = computed(() => {
  const first = galleryItems.value[0];
  if (!first) return null;
  return first.sm || first.md || first.url;
});

const cityLabel = computed(() => {
  const hotel = props.hotel;
  if (!hotel) return "";
  return hotel.city || hotel.country || "";
});

function toggleExpand() {
  userExpanded.value = !userExpanded.value;
}
</script>

<template>
  <section>
    <div v-if="!hotel" class="grid gap-4 sm:grid-cols-[160px_1fr]">
      <Skeleton class="aspect-4/5 w-full" />
      <div class="space-y-2">
        <Skeleton class="h-6 w-2/3" />
        <Skeleton class="h-4 w-1/2" />
      </div>
    </div>

    <!-- Compact summary card: shown on steps 2-4 by default so the booking
         form gets the vertical space. Click to expand back to full hero. -->
    <div
      v-else-if="isCompact"
      class="bg-card flex items-center gap-3 rounded-xl border p-2 sm:p-3"
    >
      <div class="bg-muted relative size-14 shrink-0 overflow-hidden rounded-lg sm:size-16">
        <img
          v-if="compactThumb"
          :src="compactThumb"
          :alt="hotel.name"
          class="size-full object-cover"
          loading="lazy"
        />
        <div
          v-else
          class="from-muted to-muted/40 size-full bg-linear-to-br"
        />
      </div>
      <div class="min-w-0 flex-1 space-y-0.5">
        <p class="truncate text-sm font-medium tracking-tight sm:text-base">
          {{ hotel.name }}
        </p>
        <div
          class="text-muted-foreground flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs tracking-tight sm:text-sm"
        >
          <span v-if="hotel.star_rating" class="inline-flex items-center gap-0.5">
            <Icon
              name="material-symbols:star-rounded"
              class="text-primary size-3.5"
            />
            <span>{{ hotel.star_rating }}-star</span>
          </span>
          <span v-if="hotel.star_rating && cityLabel" aria-hidden="true">·</span>
          <span v-if="cityLabel" class="truncate">{{ cityLabel }}</span>
          <span v-if="(hotel.star_rating || cityLabel) && eventDateRange" aria-hidden="true">·</span>
          <span v-if="eventDateRange" class="truncate">{{ eventDateRange }}</span>
        </div>
      </div>
      <button
        type="button"
        class="text-primary hover:bg-muted shrink-0 rounded-lg px-2.5 py-1 text-xs font-medium tracking-tight transition active:scale-95 sm:text-sm"
        @click="toggleExpand"
      >
        <span class="hidden sm:inline">View details</span>
        <Icon name="hugeicons:arrow-down-01" class="size-4 sm:hidden" />
      </button>
    </div>

    <!-- Full hero: shown on step 1, or whenever the user clicks "View details"
         on a later step. Same DOM as before — only the wrapper changed. -->
    <div
      v-else
      class="grid items-start gap-4 sm:grid-cols-[160px_1fr] lg:grid-cols-[160px_1fr_220px]"
    >
      <div class="bg-muted relative overflow-hidden rounded-xl">
        <Lightbox
          v-if="galleryItems.length"
          :items="galleryItems"
          :alt="hotel.name"
          thumbnail-key="md"
        >
          <template #trigger="{ open }">
            <button
              type="button"
              class="block size-full cursor-zoom-in"
              @click="open"
            >
              <img
                :src="galleryItems[0].md || galleryItems[0].lg || galleryItems[0].url"
                :alt="hotel.name"
                class="aspect-4/5 w-full object-cover"
                loading="lazy"
              />
              <span
                v-if="galleryItems.length > 1"
                class="absolute right-1.5 bottom-1.5 inline-flex items-center gap-1 rounded-full bg-black/55 px-2 py-0.5 text-xs font-medium tracking-tight text-white"
              >
                <Icon name="hugeicons:image-02" class="size-3" />
                {{ galleryItems.length }}
              </span>
            </button>
          </template>
        </Lightbox>
        <div
          v-else
          class="from-muted to-muted/40 aspect-4/5 w-full bg-linear-to-br"
        />
      </div>
      <div class="space-y-1.5">
        <div
          class="text-muted-foreground flex flex-wrap items-center gap-1.5 text-xs sm:text-sm tracking-tight"
        >
          <span>{{ hotel.event?.title }}</span>
          <span v-if="eventDateRange" class="text-muted-foreground/70">·</span>
          <span v-if="eventDateRange">{{ eventDateRange }}</span>
        </div>
        <h1 class="text-2xl font-medium tracking-tighter sm:text-3xl">{{ hotel.name }}</h1>
        <div class="flex flex-wrap items-center gap-1.5 pt-1">
          <span
            v-if="hotel.star_rating"
            class="text-primary border-primary/30 bg-primary/5 inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium tracking-tight"
          >
            <Icon name="material-symbols:star-rounded" class="size-3.5" />
            {{ hotel.star_rating }}-star
          </span>
          <Badge
            v-for="facility in (hotel.facilities ?? []).slice(0, 4)"
            :key="facility"
            variant="muted"
            class="text-xs"
          >
            {{ facility }}
          </Badge>
        </div>
        <a
          v-if="fullAddress && mapsLinkUrl"
          :href="mapsLinkUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="text-muted-foreground hover:text-foreground flex items-start gap-1.5 pt-1 text-sm tracking-tight"
        >
          <Icon name="hugeicons:location-04" class="mt-0.5 size-4 shrink-0" />
          <span>{{ fullAddress }}</span>
        </a>
        <p
          v-if="hotel.description"
          class="text-muted-foreground line-clamp-3 pt-1 text-sm tracking-tight"
        >
          {{ hotel.description }}
        </p>
        <!-- Only surfaced when the user has manually expanded a collapsed
             step (collapsed prop true) — gives them a clear way to re-collapse. -->
        <div v-if="collapsed && userExpanded" class="pt-2">
          <button
            type="button"
            class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-xs font-medium tracking-tight sm:text-sm"
            @click="toggleExpand"
          >
            <Icon name="hugeicons:arrow-up-01" class="size-4" />
            <span>Hide details</span>
          </button>
        </div>
      </div>
      <div
        v-if="mapEmbedUrl"
        class="bg-muted relative hidden aspect-4/5 w-full overflow-hidden rounded-xl lg:block lg:aspect-auto lg:h-full lg:min-h-[220px]"
      >
        <iframe
          :src="mapEmbedUrl"
          class="size-full"
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          allowfullscreen
          :title="`Map showing ${hotel.name} location`"
        />
      </div>
    </div>
  </section>
</template>
