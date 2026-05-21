<script setup>
import { Skeleton } from "../ui/skeleton";
import { Badge } from "../ui/badge";
import { Lightbox } from "../ui/lightbox";
import { computed } from "vue";

const props = defineProps({
  hotel: { type: Object, default: null },
  collapsed: { type: Boolean, default: false },
});

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
</script>

<template>
  <section
    class="overflow-hidden transition-all duration-300"
    :class="collapsed ? 'max-h-56' : 'max-h-[700px]'"
  >
    <div v-if="!hotel" class="grid gap-4 sm:grid-cols-[160px_1fr]">
      <Skeleton class="aspect-4/5 w-full" />
      <div class="space-y-2">
        <Skeleton class="h-6 w-2/3" />
        <Skeleton class="h-4 w-1/2" />
      </div>
    </div>
    <div v-else class="grid items-start gap-4 sm:grid-cols-[160px_1fr] lg:grid-cols-[160px_1fr_220px]">
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
          class="from-muted to-muted/40 aspect-4/5 w-full bg-gradient-to-br"
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
        <p
          v-if="!collapsed && fullAddress"
          class="text-muted-foreground flex items-start gap-1.5 pt-1 text-sm tracking-tight"
        >
          <Icon name="hugeicons:location-04" class="mt-0.5 size-4 shrink-0" />
          <span>{{ fullAddress }}</span>
        </p>
        <p
          v-if="!collapsed && hotel.description"
          class="text-muted-foreground line-clamp-3 pt-1 text-sm tracking-tight"
        >
          {{ hotel.description }}
        </p>
        <div
          v-if="!collapsed && (hotel.contact_email || hotel.contact_phone)"
          class="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 pt-2 text-sm tracking-tight"
        >
          <a
            v-if="hotel.contact_email"
            :href="`mailto:${hotel.contact_email}`"
            class="hover:text-foreground inline-flex items-center gap-1.5"
          >
            <Icon name="hugeicons:mail-01" class="size-4 shrink-0" />
            <span class="truncate">{{ hotel.contact_email }}</span>
          </a>
          <a
            v-if="hotel.contact_phone"
            :href="`tel:${hotel.contact_phone}`"
            class="hover:text-foreground inline-flex items-center gap-1.5"
          >
            <Icon name="hugeicons:call-02" class="size-4 shrink-0" />
            <span>{{ hotel.contact_phone }}</span>
          </a>
        </div>
      </div>
      <div
        v-if="!collapsed && mapEmbedUrl"
        class="bg-muted relative aspect-4/5 w-full overflow-hidden rounded-xl lg:aspect-auto lg:h-full lg:min-h-[220px]"
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
