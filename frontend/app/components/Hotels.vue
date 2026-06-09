<template>
  <section id="hotels">
    <div class="flex flex-col items-center text-center">
      <component :is="tag" class="section-title">Hotels</component>
      <p class="section-description mt-2">
        Browse our partner hotels for the event and book your stay with secure online payment.
      </p>
    </div>

    <div v-if="pending" class="mt-8 space-y-12 sm:mt-12">
      <section v-for="i in 2" :key="i" class="space-y-5">
        <div v-if="showEventHeader" class="flex items-center gap-3 border-b pb-4">
          <Skeleton class="aspect-4/5 w-16 shrink-0 rounded-lg sm:w-20" />
          <div class="space-y-2">
            <Skeleton class="h-3.5 w-24" />
            <Skeleton class="h-6 w-48" />
            <Skeleton class="h-3.5 w-56" />
          </div>
        </div>
        <div
          class="grid grid-cols-2 justify-center gap-x-2 gap-y-4 sm:grid-cols-[repeat(auto-fit,minmax(200px,240px))]"
        >
          <div v-for="j in 4" :key="j" class="row-span-5 grid grid-rows-subgrid gap-y-1">
            <Skeleton class="aspect-19/20 w-full rounded-2xl" />
            <Skeleton class="mt-1 h-9 w-4/5 self-start sm:h-11" />
            <Skeleton class="h-3.5 w-14 self-start sm:h-4" />
            <Skeleton class="h-3.5 w-2/3 self-start sm:h-4" />
            <Skeleton class="h-4 w-3/5 self-start sm:h-5" />
          </div>
        </div>
      </section>
    </div>

    <Empty v-else-if="!hotels.length" class="mt-8 border sm:mt-12">
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

    <div v-else class="mt-8 space-y-12 sm:mt-12">
      <section v-for="group in groupedHotels" :key="group.event.id" class="space-y-5">
        <div v-if="showEventHeader" class="flex items-center gap-3 border-b pb-4">
          <div class="bg-muted aspect-4/5 w-18 shrink-0 overflow-hidden rounded-lg border sm:w-20">
            <img
              v-if="group.event.poster?.md"
              :src="group.event.poster.md"
              :alt="`${group.event.title} poster`"
              class="size-full object-cover"
              loading="lazy"
              decoding="async"
            />
            <div v-else class="from-muted to-muted/40 size-full bg-linear-to-br" />
          </div>
          <div class="min-w-0">
            <p
              v-if="group.event.project?.name"
              class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm"
            >
              {{ group.event.project.name }}
            </p>
            <h3 class="mt-0.5 truncate text-lg font-semibold tracking-tighter sm:text-xl">
              {{ group.event.title }}
            </h3>
            <div
              v-if="formatEventDates(group.event) || eventVenue(group.event)"
              class="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs tracking-tight sm:text-sm"
            >
              <span v-if="formatEventDates(group.event)" class="inline-flex items-center gap-1.5">
                <Icon name="hugeicons:calendar-03" class="size-4 shrink-0" />
                {{ formatEventDates(group.event) }}
              </span>
              <template v-if="eventVenue(group.event)">
                <a
                  v-if="group.event.location_link"
                  :href="group.event.location_link"
                  target="_blank"
                  rel="noopener"
                  class="hover:text-foreground inline-flex min-w-0 items-center gap-1.5 transition-colors"
                >
                  <Icon name="hugeicons:location-04" class="size-4 shrink-0" />
                  <span class="truncate">{{ eventVenue(group.event) }}</span>
                </a>
                <span v-else class="inline-flex min-w-0 items-center gap-1.5">
                  <Icon name="hugeicons:location-04" class="size-4 shrink-0" />
                  <span class="truncate">{{ eventVenue(group.event) }}</span>
                </span>
              </template>
            </div>
          </div>
        </div>

        <div
          class="grid grid-cols-2 justify-center gap-x-2 gap-y-4 sm:grid-cols-[repeat(auto-fit,minmax(200px,240px))]"
        >
          <NuxtLink
            v-for="hotel in group.hotels"
            :key="hotel.id"
            :to="`/hotels/${group.event.slug}/${hotel.slug}`"
            class="row-span-5 grid grid-rows-subgrid gap-y-1 tracking-tight"
          >
            <div class="bg-muted aspect-19/20 overflow-hidden rounded-2xl">
              <img
                v-if="hotel.featured?.md"
                :src="hotel.featured.md"
                :alt="hotel.name"
                class="size-full object-cover"
                loading="lazy"
                decoding="async"
              />
              <div v-else class="from-muted to-muted/40 size-full bg-linear-to-br" />
            </div>
            <h4
              class="mt-1 line-clamp-2 self-start text-sm leading-snug font-semibold tracking-tighter sm:text-base"
            >
              {{ hotel.name }}
            </h4>
            <p
              class="text-muted-foreground flex items-center gap-1 self-start text-xs tracking-tight sm:text-sm"
            >
              <template v-if="hotel.star_rating">
                <Icon
                  name="material-symbols:star-rounded"
                  class="text-foreground size-4 shrink-0"
                />
                {{ hotel.star_rating }}-star
              </template>
            </p>
            <p class="text-muted-foreground self-start truncate text-xs tracking-tight sm:text-sm">
              {{ [hotel.city].filter(Boolean).join(", ") }}
            </p>
            <p class="text-muted-foreground self-start text-xs tracking-tight sm:text-sm">
              <template v-if="cheapestRate(hotel)">
                <span
                  v-tippy="{ content: rateTooltip(hotel.estimated_price), allowHTML: true, theme: 'invert', arrow: true, onShow: showTooltipIfContent }"
                  :class="{ 'cursor-help': hotel.estimated_price }"
                >
                  <span>From </span>
                  <span class="text-foreground text-sm font-semibold tracking-tighter sm:text-base">Rp{{ formatRupiah(cheapestRate(hotel)) }}</span>
                  <template v-if="formatEstimate(cheapestRate(hotel), hotel.estimated_price)">
                    <span> (≈ <span class="text-foreground text-sm font-semibold tracking-tighter sm:text-base">{{
                      formatEstimate(cheapestRate(hotel), hotel.estimated_price)
                    }}</span>)</span>
                  </template>
                  <span> / night</span>
                </span>
              </template>
            </p>
          </NuxtLink>
        </div>
      </section>
    </div>
  </section>
</template>

<script setup>
defineProps({
  tag: {
    type: String,
    default: "h2",
  },
  showEventHeader: {
    type: Boolean,
    default: true,
  },
});

const { data, pending } = useAsyncData("public-hotels", () => $fetch("/api/hotels"));

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

const eventVenue = (ev) => [ev.location, ev.hall].filter(Boolean).join(" · ");

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);

const formatEstimate = (idrAmount, est) => {
  if (!est?.currency_code || !est?.rate_per_idr || !idrAmount) {
    return null;
  }
  return new Intl.NumberFormat(undefined, {
    style: "currency",
    currency: est.currency_code,
    maximumFractionDigits: 0,
  }).format(Number(idrAmount) * Number(est.rate_per_idr));
};

const estimateUnit = (est) =>
  est?.currency_code
    ? new Intl.NumberFormat(undefined, {
        style: "currency",
        currency: est.currency_code,
        maximumFractionDigits: 0,
      }).format(1)
    : "";

const estimateIdr = (est) =>
  est?.rate_per_idr ? `Rp${formatRupiah(Math.round(1 / Number(est.rate_per_idr)))}` : "";

const flagHtml = (country) =>
  `<span class="inline-flex aspect-3/2 h-4 shrink-0 overflow-hidden rounded-sm align-middle"><img src="/flags/${country}.png" class="size-full object-cover" alt="" /></span>`;

// Built as an HTML string (not a Vue slot) so Tippy renders it lazily inside
// the popper. The component-slot approach flashes the flags inline on load
// because vue-tippy briefly shows the slot before moving it into the popper.
const rateTooltip = (est) => {
  if (!est?.currency_code || !est?.rate_per_idr) {
    return "";
  }
  const country = est.currency_code.slice(0, 2).toLowerCase();
  return `<span class="inline-flex items-center gap-2 whitespace-nowrap text-base font-medium tracking-tight">${flagHtml(country)}<span>${estimateUnit(est)}</span><span class="opacity-60">=</span>${flagHtml("id")}<span>${estimateIdr(est)}</span></span>`;
};

// Suppress the tooltip when there is no estimate. vue-tippy coerces empty
// content to null, so check truthiness rather than comparing to "".
const showTooltipIfContent = (instance) => Boolean(instance.props.content);

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
