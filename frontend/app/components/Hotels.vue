<template>
  <div class="container-wider">
    <header class="flex flex-col items-center text-center">
      <component :is="tag" class="section-title">Hotels</component>
      <p class="section-description mt-3">
        Browse our partner hotels for the event and book your stay with secure
        online payment. Your check-in voucher is sent straight to your email.
      </p>
    </header>

    <div v-if="pending" class="mt-10 space-y-12 sm:mt-12">
      <section v-for="i in 2" :key="i" class="space-y-5">
        <div class="flex items-end justify-between gap-3 border-b pb-3">
          <div class="space-y-2">
            <Skeleton class="h-6 w-48" />
            <Skeleton class="h-4 w-32" />
          </div>
          <Skeleton class="h-4 w-16" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          <div
            v-for="j in 4"
            :key="j"
            class="bg-card overflow-hidden rounded-2xl border"
          >
            <Skeleton class="aspect-4/5 w-full rounded-none" />
            <div class="space-y-2 p-4">
              <Skeleton class="h-5 w-3/4" />
              <Skeleton class="h-3.5 w-full" />
              <Skeleton class="h-4 w-1/2" />
            </div>
          </div>
        </div>
      </section>
    </div>

    <Empty v-else-if="!hotels.length" class="mt-10 border sm:mt-12">
      <EmptyHeader>
        <EmptyMedia variant="icon">
          <Icon name="hugeicons:hotel-01" />
        </EmptyMedia>
        <EmptyTitle>No hotels available yet</EmptyTitle>
        <EmptyDescription>
          Hotels will appear here once they are attached to an active event.
        </EmptyDescription>
      </EmptyHeader>
    </Empty>

    <div v-else class="mt-10 space-y-12 sm:mt-12">
      <section
        v-for="group in groupedHotels"
        :key="group.event.id"
        class="space-y-5"
      >
        <div class="flex items-end justify-between gap-3 border-b pb-3">
          <div class="min-w-0">
            <h3 class="truncate text-lg font-semibold tracking-tighter sm:text-xl">
              {{ group.event.title }}
            </h3>
            <p
              v-if="group.event.start_date || group.event.end_date"
              class="text-muted-foreground mt-0.5 text-xs tracking-tight sm:text-sm"
            >
              {{ formatEventDates(group.event) }}
            </p>
          </div>
          <span
            class="text-muted-foreground shrink-0 text-xs tracking-tight sm:text-sm"
          >
            {{ group.hotels.length }} hotel{{ group.hotels.length > 1 ? "s" : "" }}
          </span>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          <NuxtLink
            v-for="hotel in group.hotels"
            :key="hotel.id"
            :to="`/hotels/${group.event.slug}/${hotel.slug}`"
            class="group bg-card hover:border-foreground/20 flex flex-col overflow-hidden rounded-2xl border tracking-tight transition-colors"
          >
            <div class="bg-muted aspect-4/5 overflow-hidden">
              <img
                v-if="hotel.featured?.md"
                :src="hotel.featured.md"
                :alt="hotel.name"
                class="size-full object-cover transition duration-500 group-hover:scale-[1.03]"
                loading="lazy"
                decoding="async"
              />
              <div
                v-else
                class="from-muted to-muted/40 size-full bg-gradient-to-br"
              />
            </div>
            <div class="flex flex-1 flex-col gap-1 p-4">
              <h4 class="line-clamp-1 text-base font-semibold tracking-tighter">
                {{ hotel.name }}
              </h4>
              <p
                class="text-muted-foreground line-clamp-1 text-xs tracking-tight sm:text-sm"
              >
                {{ [hotel.address, hotel.city].filter(Boolean).join(", ") }}
              </p>
              <p
                v-if="cheapestRate(hotel)"
                class="mt-auto pt-2 text-sm tracking-tight"
              >
                <span class="text-muted-foreground">From</span>
                <span class="text-foreground mx-1 font-medium tabular-nums">
                  Rp{{ formatRupiah(cheapestRate(hotel)) }}
                </span>
                <span class="text-muted-foreground">/ night</span>
              </p>
            </div>
          </NuxtLink>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import {
  Empty,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "./ui/empty";
import { Skeleton } from "./ui/skeleton";

defineProps({
  tag: {
    type: String,
    default: "h2",
  },
});

const { data, pending } = useAsyncData("public-hotels", () =>
  $fetch("/api/hotels"),
);

const hotels = computed(() => data.value?.data ?? []);

const groupedHotels = computed(() => {
  const map = new Map();
  for (const hotel of hotels.value) {
    if (!hotel.event) {
      continue;
    }
    const key = hotel.event.id;
    if (!map.has(key)) {
      map.set(key, { event: hotel.event, hotels: [] });
    }
    map.get(key).hotels.push(hotel);
  }
  return Array.from(map.values());
});

const cheapestRate = (hotel) => {
  if (!hotel.room_types?.length) {
    return null;
  }
  return Math.min(...hotel.room_types.map((r) => r.base_rate));
};

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);

const formatEventDates = (ev) => {
  const fmt = (d) =>
    d
      ? new Date(d).toLocaleDateString("en-GB", {
          day: "numeric",
          month: "short",
          year: "numeric",
        })
      : "";
  if (ev.start_date && ev.end_date) {
    return `${fmt(ev.start_date)} - ${fmt(ev.end_date)}`;
  }
  return fmt(ev.start_date || ev.end_date);
};
</script>
