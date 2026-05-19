<template>
  <div class="mx-auto flex flex-col gap-y-12 pt-4 pb-16 sm:gap-y-14 lg:max-w-5xl xl:max-w-6xl">
    <div v-if="pending" class="space-y-6">
      <div class="grid gap-5 lg:grid-cols-[14rem_1fr_14rem]">
        <Skeleton class="aspect-[4/5] w-full rounded-xl lg:w-56" />
        <div class="space-y-3 lg:pt-3">
          <Skeleton class="h-4 w-20" />
          <Skeleton class="h-8 w-3/4" />
          <Skeleton class="h-5 w-40" />
          <div class="space-y-2 pt-2">
            <Skeleton class="h-4 w-2/3" />
            <Skeleton class="h-4 w-1/2" />
          </div>
        </div>
        <Skeleton class="aspect-[4/5] w-full rounded-xl lg:w-56" />
      </div>
    </div>

    <Empty v-else-if="!hotel" class="border">
      <EmptyHeader>
        <EmptyMedia variant="icon">
          <Icon name="hugeicons:hotel-01" />
        </EmptyMedia>
        <EmptyTitle>Hotel not found</EmptyTitle>
        <EmptyDescription>
          This hotel may have been deleted. Check the trash or return to the hotels list.
        </EmptyDescription>
      </EmptyHeader>
      <EmptyContent>
        <Button as-child variant="outline" size="sm">
          <NuxtLink to="/hotels-master">
            <Icon name="hugeicons:arrow-left-01" class="size-4 shrink-0" />
            Back to hotels
          </NuxtLink>
        </Button>
      </EmptyContent>
    </Empty>

    <template v-else>
      <div class="space-y-5">
        <ButtonBack destination="/hotels-master" />

        <div class="grid gap-5 lg:grid-cols-[14rem_1fr_14rem] lg:items-start">
          <div class="bg-muted aspect-[4/5] w-full overflow-hidden rounded-xl lg:w-56">
            <Lightbox
              v-if="galleryItems.length"
              :items="galleryItems"
              :alt="hotel.name"
              thumbnail-key="md"
            >
              <template #trigger="{ open }">
                <button
                  type="button"
                  class="group/poster relative block size-full cursor-zoom-in"
                  @click="open"
                >
                  <img
                    :src="galleryItems[0].md || galleryItems[0].lg || galleryItems[0].url"
                    :alt="hotel.name"
                    class="size-full object-cover transition-transform duration-300 select-none group-hover/poster:scale-[1.02]"
                  />
                  <span
                    v-if="galleryItems.length > 1"
                    class="bg-background/85 absolute right-2 bottom-2 inline-flex items-center gap-x-1 rounded-full px-2 py-0.5 text-xs tracking-tight opacity-0 backdrop-blur-sm transition-opacity duration-200 group-hover/poster:opacity-100"
                  >
                    <Icon name="hugeicons:image-02" class="size-3.5" />
                    {{ galleryItems.length }}
                  </span>
                </button>
              </template>
            </Lightbox>
            <div v-else class="from-muted to-muted/40 size-full bg-gradient-to-br" />
          </div>

          <div class="flex min-w-0 flex-col items-start gap-y-3 lg:pt-2">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
              <span
                v-if="hotel.star_rating"
                class="text-primary inline-flex items-center gap-x-0.5 text-sm font-medium tracking-tight"
              >
                <Icon
                  v-for="i in Number(hotel.star_rating)"
                  :key="i"
                  name="material-symbols:star-rounded"
                  class="size-4"
                />
              </span>
              <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">·</span>
              <span
                class="inline-flex items-center gap-x-1.5 text-xs tracking-tight sm:text-sm"
                :class="
                  hotel.is_active ? 'text-success-foreground' : 'text-muted-foreground'
                "
              >
                <span
                  class="inline-block size-1.5 rounded-full"
                  :class="hotel.is_active ? 'bg-success' : 'bg-muted-foreground/50'"
                />
                {{ hotel.is_active ? "Active" : "Inactive" }}
              </span>
            </div>

            <h1 class="text-2xl font-semibold tracking-tighter sm:text-3xl">
              {{ hotel.name }}
            </h1>

            <dl class="grid w-full gap-x-8 gap-y-3 pt-1 sm:grid-cols-2">
              <div v-for="meta in metaItems" :key="meta.label" class="min-w-0">
                <dt
                  class="text-muted-foreground inline-flex items-center gap-x-1.5 text-xs tracking-tight sm:text-sm"
                >
                  <Icon :name="meta.icon" class="size-3.5 shrink-0" />
                  {{ meta.label }}
                </dt>
                <dd class="mt-0.5 text-sm tracking-tight sm:text-base">
                  <a
                    v-if="meta.href"
                    :href="meta.href"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="hover:text-primary inline-flex items-center gap-x-1 font-medium tracking-tight underline-offset-2 hover:underline"
                  >
                    {{ meta.value }}
                    <Icon name="hugeicons:link-square-02" class="size-3.5" />
                  </a>
                  <span v-else class="font-medium tracking-tight">{{ meta.value }}</span>
                </dd>
              </div>
            </dl>

            <p
              v-if="hotel.description"
              class="text-body max-w-prose text-sm tracking-tight whitespace-pre-line sm:text-base"
            >
              {{ hotel.description }}
            </p>

            <div v-if="canEdit" class="flex flex-wrap items-center gap-2 pt-1">
              <Button as-child size="sm">
                <NuxtLink :to="`/hotels-master/${hotelSlug}/edit`">
                  <Icon name="hugeicons:edit-02" class="size-4" />
                  Edit Hotel
                </NuxtLink>
              </Button>
            </div>
          </div>

          <div class="bg-muted aspect-[4/5] w-full overflow-hidden rounded-xl lg:w-56">
            <iframe
              v-if="mapEmbedUrl"
              :src="mapEmbedUrl"
              class="size-full"
              loading="lazy"
              referrerpolicy="no-referrer-when-downgrade"
              allowfullscreen
            />
            <div v-else class="from-muted to-muted/40 size-full bg-gradient-to-br" />
          </div>
        </div>
      </div>

      <section v-if="galleryThumbs.length" class="space-y-4">
        <div class="flex items-baseline justify-between gap-3">
          <h2 class="text-lg font-semibold tracking-tighter sm:text-xl">Gallery</h2>
          <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">
            {{ galleryThumbs.length }} photo{{ galleryThumbs.length === 1 ? "" : "s" }}
          </span>
        </div>
        <Lightbox :items="galleryItems" :alt="hotel.name" thumbnail-key="md">
          <template #trigger="{ open, openAt }">
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
              <button
                v-for="(item, i) in galleryThumbs.slice(0, 8)"
                :key="item.id || i"
                type="button"
                class="bg-muted relative aspect-[4/3] overflow-hidden rounded-lg"
                @click="openAt ? openAt(i + featuredOffset) : open()"
              >
                <img
                  :src="item.sm || item.md || item.url"
                  :alt="hotel.name"
                  loading="lazy"
                  class="size-full cursor-zoom-in object-cover transition-transform duration-300 hover:scale-[1.04]"
                />
                <span
                  v-if="i === 7 && galleryThumbs.length > 8"
                  class="bg-background/85 absolute inset-0 flex items-center justify-center text-sm font-medium tracking-tight backdrop-blur-sm"
                >
                  +{{ galleryThumbs.length - 8 }} more
                </span>
              </button>
            </div>
          </template>
        </Lightbox>
      </section>

      <section class="space-y-4">
        <div class="flex items-baseline justify-between gap-3">
          <h2 class="text-lg font-semibold tracking-tighter sm:text-xl">Room types</h2>
          <span
            v-if="hotel.room_types?.length"
            class="text-muted-foreground text-xs tracking-tight sm:text-sm"
          >
            {{ hotel.room_types.length }} room type{{
              hotel.room_types.length === 1 ? "" : "s"
            }}
          </span>
        </div>

        <Empty v-if="!hotel.room_types?.length" class="border">
          <EmptyHeader>
            <EmptyMedia variant="icon">
              <Icon name="hugeicons:bed-single-01" />
            </EmptyMedia>
            <EmptyTitle>No room types yet</EmptyTitle>
            <EmptyDescription>
              Add room types from an event's hotel page to make this hotel bookable.
            </EmptyDescription>
          </EmptyHeader>
        </Empty>

        <ul v-else class="space-y-3">
          <li
            v-for="room in hotel.room_types"
            :key="room.id"
            class="bg-card overflow-hidden rounded-xl border transition-colors"
            :class="room.deleted_at ? 'opacity-60' : 'hover:border-border/80'"
          >
            <div class="grid gap-0 sm:grid-cols-[11rem_1fr]">
              <Lightbox
                v-if="room.gallery?.length"
                :items="room.gallery"
                :alt="room.name"
                rounded="rounded-none"
              >
                <template #trigger="{ open }">
                  <button
                    type="button"
                    class="bg-muted relative aspect-[4/3] w-full overflow-hidden sm:aspect-auto sm:h-full sm:min-h-[9rem]"
                    @click="open"
                  >
                    <img
                      :src="room.gallery[0].md || room.gallery[0].sm || room.gallery[0].url"
                      :alt="room.name"
                      loading="lazy"
                      class="size-full cursor-zoom-in object-cover transition-transform duration-300 hover:scale-[1.03]"
                    />
                    <span
                      v-if="room.gallery.length > 1"
                      class="bg-background/90 absolute right-1.5 bottom-1.5 inline-flex items-center gap-x-1 rounded-full px-2 py-0.5 text-xs tracking-tight backdrop-blur-sm"
                    >
                      <Icon name="hugeicons:image-02" class="size-3.5" />
                      {{ room.gallery.length }}
                    </span>
                  </button>
                </template>
              </Lightbox>
              <div
                v-else
                class="from-muted to-muted/40 aspect-[4/3] w-full bg-gradient-to-br sm:aspect-auto sm:h-full sm:min-h-[9rem]"
              />

              <div class="flex flex-col gap-2 p-4 sm:gap-3 sm:p-5">
                <div class="flex items-start justify-between gap-4">
                  <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                      <h3 class="text-base font-semibold tracking-tighter sm:text-lg">
                        {{ room.name }}
                      </h3>
                      <span
                        v-if="!room.is_active"
                        class="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-xs tracking-tight"
                      >
                        Inactive
                      </span>
                      <span
                        v-if="room.deleted_at"
                        class="bg-destructive/15 text-destructive-foreground rounded-full px-2 py-0.5 text-xs tracking-tight"
                      >
                        Trashed
                      </span>
                    </div>
                    <div
                      v-if="roomSpecs(room).length"
                      class="text-muted-foreground mt-1.5 flex flex-wrap gap-x-3 gap-y-1"
                    >
                      <span
                        v-for="spec in roomSpecs(room)"
                        :key="spec.text"
                        class="inline-flex items-center gap-x-1 text-xs tracking-tight sm:text-sm"
                      >
                        <Icon :name="spec.icon" class="size-3.5 shrink-0" />
                        {{ spec.text }}
                      </span>
                    </div>
                  </div>
                  <div class="shrink-0 text-right tracking-tight">
                    <div class="text-base font-semibold tabular-nums sm:text-lg">
                      Rp{{ fmtRupiah(room.base_rate) }}
                    </div>
                    <div class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                      per night
                    </div>
                  </div>
                </div>
                <p
                  v-if="room.description"
                  class="text-body line-clamp-2 text-sm tracking-tight"
                >
                  {{ room.description }}
                </p>
              </div>
            </div>
          </li>
        </ul>
      </section>

      <section class="space-y-4">
        <div class="flex items-baseline justify-between gap-3">
          <h2 class="text-lg font-semibold tracking-tighter sm:text-xl">Attached events</h2>
          <span
            v-if="hotel.events?.length"
            class="text-muted-foreground text-xs tracking-tight sm:text-sm"
          >
            {{ hotel.events.length }} event{{ hotel.events.length === 1 ? "" : "s" }}
          </span>
        </div>

        <Empty v-if="!hotel.events?.length" class="border">
          <EmptyHeader>
            <EmptyMedia variant="icon">
              <Icon name="hugeicons:calendar-04" />
            </EmptyMedia>
            <EmptyTitle>Not attached to any event yet</EmptyTitle>
            <EmptyDescription>
              Open an event and use Attach Hotel to make this hotel bookable for that event.
            </EmptyDescription>
          </EmptyHeader>
        </Empty>

        <ul v-else class="divide-border divide-y overflow-hidden rounded-xl border">
          <li
            v-for="ev in hotel.events"
            :key="ev.id"
            class="hover:bg-muted/40 flex flex-wrap items-center justify-between gap-3 px-4 py-3 transition-colors sm:px-5 sm:py-4"
          >
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <p class="text-sm font-medium tracking-tight sm:text-base">
                  {{ ev.title || ev.name }}
                </p>
                <span
                  class="inline-flex items-center gap-x-1.5 text-xs tracking-tight"
                  :class="
                    ev.pivot?.is_active !== false
                      ? 'text-success-foreground'
                      : 'text-muted-foreground'
                  "
                >
                  <span
                    class="inline-block size-1.5 rounded-full"
                    :class="
                      ev.pivot?.is_active !== false ? 'bg-success' : 'bg-muted-foreground/50'
                    "
                  />
                  {{ ev.pivot?.is_active !== false ? "Active" : "Inactive" }}
                </span>
              </div>
              <p class="text-muted-foreground mt-0.5 text-xs tracking-tight sm:text-sm">
                <span v-if="ev.project?.name">{{ ev.project.name }}</span
                ><span v-if="ev.project?.name"> · </span>{{ ev.slug
                }}<span v-if="ev.pivot?.notes"> · {{ ev.pivot.notes }}</span>
              </p>
            </div>
            <NuxtLink
              v-if="ev.project?.username && ev.slug"
              :to="`/projects/${ev.project.username}/events/${ev.slug}/hotels/${hotelSlug}`"
              class="text-muted-foreground hover:text-foreground inline-flex items-center gap-x-1 text-xs tracking-tight underline-offset-2 transition-colors hover:underline sm:text-sm"
            >
              Open in event
              <Icon name="hugeicons:arrow-right-01" class="size-3.5" />
            </NuxtLink>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";
import { Lightbox } from "@/components/ui/lightbox";
import { Skeleton } from "@/components/ui/skeleton";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["hotels.read"],
  layout: "app",
});

const route = useRoute();
const hotelSlug = computed(() => route.params.hotelSlug);

const { hasPermission } = usePermission();
const canEdit = computed(() => hasPermission("hotels.update"));

const { data, pending } = await useLazySanctumFetch(
  () => `/api/hotels/${hotelSlug.value}`,
  { key: () => `hotel-master-detail-${hotelSlug.value}` }
);

const hotel = computed(() => data.value?.data);

usePageMeta(null, {
  title: computed(() => `${hotel.value?.name ?? "Hotel"} · Master`),
});

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
    },
    {
      label: "Commission / Tax / Service",
      icon: "hugeicons:percent-square",
      value: `${Number(hotel.value.commission_rate).toFixed(2)}% / ${Number(hotel.value.tax_percentage).toFixed(2)}% / ${Number(hotel.value.service_charge_percentage).toFixed(2)}%`,
    },
  ];
});

const galleryThumbs = computed(() => {
  if (!hotel.value?.gallery?.length) return [];
  return hotel.value.gallery.map((media) => ({
    id: media.id,
    sm: media.sm,
    md: media.md,
    lg: media.lg || media.url,
    url: media.url,
    alt: hotel.value.name,
  }));
});

const featuredOffset = computed(() => (hotel.value?.featured ? 1 : 0));

const galleryItems = computed(() => {
  if (!hotel.value) return [];
  const items = [];
  if (hotel.value.featured) {
    items.push({
      id: `featured-${hotel.value.featured.id ?? "x"}`,
      sm: hotel.value.featured.sm,
      md: hotel.value.featured.md,
      lg: hotel.value.featured.lg || hotel.value.featured.url,
      url: hotel.value.featured.url,
      alt: hotel.value.name,
    });
  }
  items.push(...galleryThumbs.value);
  return items;
});

const fmtRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);

const roomSpecs = (room) => {
  const chips = [];
  if (room.bed_type) chips.push({ icon: "hugeicons:bed-single-01", text: room.bed_type });
  if (room.max_pax) chips.push({ icon: "hugeicons:user-multiple-02", text: `${room.max_pax} pax` });
  if (room.area_sqm) chips.push({ icon: "hugeicons:resize-01", text: `${room.area_sqm} m²` });
  if (room.breakfast_included) chips.push({ icon: "hugeicons:coffee-02", text: "Breakfast" });
  return chips;
};

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

  return null;
});
</script>
