<template>
  <div class="mx-auto max-w-5xl px-4 pt-4 pb-24 sm:pb-16 space-y-6">
    <Breadcrumb>
      <BreadcrumbList>
        <BreadcrumbItem>
          <BreadcrumbLink as-child>
            <NuxtLink to="/accommodation">Hotels</NuxtLink>
          </BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <BreadcrumbPage>{{ hotel?.event?.title ?? "Event" }}</BreadcrumbPage>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <BreadcrumbPage>{{ hotel?.name ?? "Hotel" }}</BreadcrumbPage>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <div v-if="pending" class="space-y-6">
      <Skeleton class="h-6 w-40" />
      <Skeleton class="aspect-video w-full" />
      <div class="space-y-3">
        <Skeleton class="h-8 w-3/4" />
        <Skeleton class="h-4 w-1/2" />
      </div>
      <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <div class="space-y-4">
          <Skeleton class="h-32 w-full" />
          <Skeleton class="h-32 w-full" />
        </div>
        <Skeleton class="h-72 w-full" />
      </div>
    </div>

    <div
      v-else-if="!hotel"
      class="text-muted-foreground rounded-md border border-dashed py-12 text-center text-sm tracking-tight"
    >
      Hotel not found.
    </div>

    <ClientOnly v-else>
      <div class="space-y-6">
        <div v-if="hotel.event" class="text-muted-foreground text-xs sm:text-sm tracking-tight">
          <span>{{ hotel.event.title }}</span>
          <span v-if="hotel.event.start_date || hotel.event.end_date">
            · {{ formatEventDates(hotel.event) }}
          </span>
        </div>

        <HotelDetailHeader :hotel="hotel" />

        <section class="grid gap-6 lg:grid-cols-[1fr_360px]">
          <div class="space-y-6">
            <RoomTypeSelector :rooms="hotel.room_types" v-model="selectedRoomQty" />
            <TransferSelector :options="hotel.transfer_options" v-model="selectedTransfers" />
          </div>

          <aside class="hidden lg:block">
            <BookingSummary
              v-model:check-in="checkInDate"
              v-model:check-out="checkOutDate"
              :summary="summary"
              :tax-percentage="hotel.tax_percentage"
              :service-percentage="hotel.service_charge_percentage"
              :event-start="hotel.event?.start_date"
              :event-end="hotel.event?.end_date"
              :can-proceed="canProceed"
              @continue="goToBooking"
            />
          </aside>
        </section>

        <div
          class="bg-background/95 fixed inset-x-0 bottom-0 z-20 border-t backdrop-blur lg:hidden"
        >
          <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
            <div class="min-w-0">
              <p class="text-muted-foreground text-xs tracking-tight">Total</p>
              <p class="font-semibold tabular-nums tracking-tight">
                Rp {{ formatRupiah(summary.total) }}
              </p>
              <p v-if="nights > 0" class="text-muted-foreground text-xs tracking-tight">
                {{ nights }} night{{ nights > 1 ? "s" : "" }} ·
                {{ totalRoomsSelected }} room{{ totalRoomsSelected > 1 ? "s" : "" }}
              </p>
            </div>
            <Button :disabled="!canProceed" @click="openMobileSummary = true">Continue</Button>
          </div>
        </div>

        <DialogResponsive
          v-model:open="openMobileSummary"
          :overflow-content="true"
          dialog-max-width="32rem"
        >
          <template #default>
            <div class="px-4 pb-6 md:px-6 md:py-5">
              <h3 class="text-lg font-semibold tracking-tight">Booking Summary</h3>
              <p class="text-muted-foreground text-sm tracking-tight mt-1">
                Review your selection before continuing.
              </p>
              <div class="mt-4">
                <BookingSummary
                  v-model:check-in="checkInDate"
                  v-model:check-out="checkOutDate"
                  :summary="summary"
                  :tax-percentage="hotel.tax_percentage"
                  :service-percentage="hotel.service_charge_percentage"
                  :event-start="hotel.event?.start_date"
                  :event-end="hotel.event?.end_date"
                  :can-proceed="canProceed"
                  @continue="goToBooking"
                />
              </div>
            </div>
          </template>
        </DialogResponsive>
      </div>

      <template #fallback>
        <div class="space-y-6">
          <Skeleton class="aspect-video w-full" />
          <div class="space-y-3">
            <Skeleton class="h-8 w-3/4" />
            <Skeleton class="h-4 w-1/2" />
          </div>
          <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
            <div class="space-y-4">
              <Skeleton class="h-32 w-full" />
              <Skeleton class="h-32 w-full" />
            </div>
            <Skeleton class="h-72 w-full" />
          </div>
        </div>
      </template>
    </ClientOnly>
  </div>
</template>

<script setup>
import HotelDetailHeader from "@/components/accommodation/HotelDetailHeader.vue";
import RoomTypeSelector from "@/components/accommodation/RoomTypeSelector.vue";
import TransferSelector from "@/components/accommodation/TransferSelector.vue";
import BookingSummary from "@/components/accommodation/BookingSummary.vue";
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useBookingSession } from "@/composables/useBookingSession";
import { computed, onMounted, ref, watch } from "vue";

definePageMeta({
  layout: "public",
});

const route = useRoute();
const router = useRouter();
const eventSlug = computed(() => route.params.eventSlug);
const hotelSlug = computed(() => route.params.hotelSlug);

const { data, pending } = await useLazyAsyncData(
  () => `public-hotel-${eventSlug.value}-${hotelSlug.value}`,
  () => $fetch(`/api/accommodation/events/${eventSlug.value}/hotels/${hotelSlug.value}`),
);

const hotel = computed(() => data.value?.data);

usePageMeta(null, {
  title: computed(() => `${hotel.value?.name ?? "Hotel"} · ${hotel.value?.event?.title ?? "Accommodation"}`),
});

const { state, hydrate, set } = useBookingSession();

const today = new Date();
today.setHours(0, 0, 0, 0);
const tomorrow = new Date(today.getTime() + 86400000);
const dayAfter = new Date(today.getTime() + 2 * 86400000);

const parseIso = (iso) => {
  if (!iso) return null;
  const [y, m, d] = iso.split("-").map(Number);
  if (!y || !m || !d) return null;
  const date = new Date(y, m - 1, d);
  return Number.isNaN(date.getTime()) ? null : date;
};

const toIsoDate = (date) => {
  if (!date) return null;
  const pad = (n) => String(n).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
};

const checkInDate = ref(tomorrow);
const checkOutDate = ref(dayAfter);
const selectedRoomQty = ref({});
const selectedTransfers = ref({});
const openMobileSummary = ref(false);
const syncing = ref(false);

onMounted(() => {
  hydrate();
  syncing.value = true;
  const sameSelection =
    state.value.eventSlug === eventSlug.value && state.value.hotelSlug === hotelSlug.value;

  if (sameSelection) {
    const savedIn = parseIso(state.value.checkIn);
    const savedOut = parseIso(state.value.checkOut);
    if (savedIn) checkInDate.value = savedIn;
    if (savedOut) checkOutDate.value = savedOut;
    selectedRoomQty.value = { ...(state.value.rooms || {}) };
    selectedTransfers.value = { ...(state.value.transfers || {}) };
  }

  syncing.value = false;

  set({
    checkIn: toIsoDate(checkInDate.value),
    checkOut: toIsoDate(checkOutDate.value),
  });
});

watch(
  hotel,
  (value) => {
    if (!value || !import.meta.client) return;
    set({
      hotelId: value.id,
      eventSlug: eventSlug.value,
      hotelSlug: hotelSlug.value,
    });
  },
  { immediate: true },
);

watch([checkInDate, checkOutDate], ([inDate, outDate]) => {
  if (syncing.value) return;
  if (inDate && outDate && outDate.getTime() <= inDate.getTime()) {
    checkOutDate.value = new Date(inDate.getTime() + 86400000);
    return;
  }
  set({ checkIn: toIsoDate(inDate), checkOut: toIsoDate(outDate) });
});

watch(
  selectedRoomQty,
  (value) => {
    if (syncing.value) return;
    set({ rooms: { ...value } });
  },
  { deep: true },
);

watch(
  selectedTransfers,
  (value) => {
    if (syncing.value) return;
    set({ transfers: { ...value } });
  },
  { deep: true },
);

const nights = computed(() => {
  if (!checkInDate.value || !checkOutDate.value) return 0;
  const ms = checkOutDate.value.getTime() - checkInDate.value.getTime();
  return Math.max(0, Math.round(ms / 86400000));
});

const totalRoomsSelected = computed(() =>
  Object.values(selectedRoomQty.value).reduce((sum, qty) => sum + (Number(qty) || 0), 0),
);

const summary = computed(() => {
  if (!hotel.value) return { rooms: 0, transfer: 0, tax: 0, service: 0, total: 0 };
  let rooms = 0;
  for (const room of hotel.value.room_types ?? []) {
    const qty = Number(selectedRoomQty.value[room.id]) || 0;
    rooms += room.base_rate * nights.value * qty;
  }
  let transfer = 0;
  for (const opt of hotel.value.transfer_options ?? []) {
    if (selectedTransfers.value[opt.id]) transfer += opt.price;
  }
  const taxBase = rooms + transfer;
  const tax = Math.round(taxBase * (hotel.value.tax_percentage / 100) * 100) / 100;
  const service =
    Math.round(taxBase * (hotel.value.service_charge_percentage / 100) * 100) / 100;
  const total = taxBase + tax + service;
  return { rooms, transfer, tax, service, total };
});

const canProceed = computed(
  () => summary.value.total > 0 && nights.value > 0 && totalRoomsSelected.value > 0,
);

const goToBooking = () => {
  if (!canProceed.value) return;
  openMobileSummary.value = false;
  router.push(`/accommodation/${eventSlug.value}/${hotelSlug.value}/booking`);
};

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);

const formatEventDates = (ev) => {
  const fmt = (d) =>
    d ? new Date(d).toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" }) : "";
  if (ev.start_date && ev.end_date) return `${fmt(ev.start_date)} - ${fmt(ev.end_date)}`;
  return fmt(ev.start_date || ev.end_date);
};
</script>
