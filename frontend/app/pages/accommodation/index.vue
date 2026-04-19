<template>
  <div class="mx-auto max-w-6xl px-4 pt-4 pb-16 space-y-6">
    <div class="space-y-2 max-w-2xl">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:building-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Hotel Accommodation</h1>
      </div>
      <p class="text-muted-foreground text-sm sm:text-base tracking-tight">
        Browse our partner hotels for each active event. Secure payment via Xendit, and your check-in voucher will be sent directly to your email.
      </p>
    </div>

    <div v-if="pending" class="flex justify-center py-10">
      <Spinner class="size-6" />
    </div>

    <div
      v-else-if="!hotels.length"
      class="text-muted-foreground rounded-md border border-dashed py-12 text-center text-sm tracking-tight"
    >
      No hotels available for active events.
    </div>

    <div v-else class="space-y-8">
      <section v-for="group in groupedHotels" :key="group.event.id" class="space-y-4">
        <div class="flex items-end justify-between gap-3 border-b pb-3">
          <div>
            <h2 class="text-lg sm:text-xl font-semibold tracking-tight">{{ group.event.title }}</h2>
            <p
              v-if="group.event.start_date || group.event.end_date"
              class="text-muted-foreground text-xs sm:text-sm tracking-tight"
            >
              {{ formatEventDates(group.event) }}
            </p>
          </div>
          <span class="text-muted-foreground text-xs sm:text-sm tracking-tight">
            {{ group.hotels.length }} hotel{{ group.hotels.length > 1 ? "s" : "" }}
          </span>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <NuxtLink
            v-for="hotel in group.hotels"
            :key="hotel.id"
            :to="`/accommodation/${group.event.slug}/${hotel.slug}`"
            class="bg-card hover:bg-muted/50 group rounded-lg border overflow-hidden transition tracking-tight"
          >
            <div class="bg-muted aspect-3/2 overflow-hidden">
              <img
                v-if="hotel.featured?.md"
                :src="hotel.featured.md"
                :alt="hotel.name"
                class="size-full object-cover transition group-hover:scale-[1.02]"
              />
              <div v-else class="text-muted-foreground flex size-full items-center justify-center">
                <Icon name="hugeicons:building-01" class="size-12" />
              </div>
            </div>
            <div class="p-4 space-y-1">
              <h3 class="text-base font-semibold tracking-tight">{{ hotel.name }}</h3>
              <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
                {{ [hotel.address, hotel.city].filter(Boolean).join(", ") }}
              </p>
              <p v-if="cheapestRate(hotel)" class="text-sm tracking-tight pt-1">
                <span class="text-muted-foreground">From</span>
                <span class="font-medium ml-1">Rp {{ formatRupiah(cheapestRate(hotel)) }}</span>
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
import { Spinner } from "@/components/ui/spinner";

definePageMeta({
  layout: "public",
});

usePageMeta(null, {
  title: "Hotel Accommodation",
  description: "Book hotel rooms with secure payment via Xendit",
});

const { data, pending } = await useLazyAsyncData("public-hotels", () =>
  $fetch("/api/accommodation/hotels"),
);

const hotels = computed(() => data.value?.data ?? []);

const groupedHotels = computed(() => {
  const map = new Map();
  for (const hotel of hotels.value) {
    if (!hotel.event) continue;
    const key = hotel.event.id;
    if (!map.has(key)) {
      map.set(key, { event: hotel.event, hotels: [] });
    }
    map.get(key).hotels.push(hotel);
  }
  return Array.from(map.values());
});

const cheapestRate = (hotel) => {
  if (!hotel.room_types?.length) return null;
  return Math.min(...hotel.room_types.map((r) => r.base_rate));
};

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);

const formatEventDates = (ev) => {
  const fmt = (d) =>
    d ? new Date(d).toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" }) : "";
  if (ev.start_date && ev.end_date) return `${fmt(ev.start_date)} - ${fmt(ev.end_date)}`;
  return fmt(ev.start_date || ev.end_date);
};
</script>
